<?php

namespace Modules\Wallet\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Customers\Models\Customer;
use Modules\Wallet\Exceptions\IdempotencyConflict;
use Modules\Wallet\Models\LedgerEntry;
use Modules\Wallet\Services\WalletService;
use Tests\TestCase;

class WalletTest extends TestCase
{
    use RefreshDatabase;

    private WalletService $wallet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wallet = app(WalletService::class);
    }

    public function test_balance_is_the_sum_of_the_history(): void
    {
        $customer = Customer::factory()->create();

        $this->wallet->topUp($customer, 5000, 'a');
        $this->wallet->charge($customer->id, 1200, 'b');
        $this->wallet->refund($customer, 200, 'c');

        $this->assertSame(4000, $this->wallet->balance($customer));
        $this->assertSame(3, LedgerEntry::count());
    }

    public function test_retrying_a_top_up_does_not_add_money_twice(): void
    {
        $customer = Customer::factory()->create();

        $first = $this->wallet->topUp($customer, 5000, 'req-1');
        $retry = $this->wallet->topUp($customer, 5000, 'req-1');

        $this->assertTrue($first->is($retry));
        $this->assertSame(5000, $this->wallet->balance($customer));
        $this->assertSame(1, LedgerEntry::count());
    }

    public function test_same_key_with_another_amount_is_refused(): void
    {
        $customer = Customer::factory()->create();
        $this->wallet->topUp($customer, 5000, 'req-1');

        $this->expectException(IdempotencyConflict::class);
        $this->wallet->topUp($customer, 9000, 'req-1');
    }

    public function test_amount_must_be_positive(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->wallet->topUp(Customer::factory()->create(), 0, 'x');
    }

    public function test_database_refuses_edits_and_deletes(): void
    {
        $entry = $this->wallet->topUp(Customer::factory()->create(), 5000, 'req-1');

        try {
            LedgerEntry::whereKey($entry->id)->update(['amount' => 1]);
            $this->fail('update went through');
        } catch (QueryException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        $this->expectException(QueryException::class);
        LedgerEntry::whereKey($entry->id)->delete();
    }

    public function test_database_refuses_a_charge_that_adds_money(): void
    {
        $customer = Customer::factory()->create();

        $this->expectException(QueryException::class);
        LedgerEntry::create([
            'customer_id' => $customer->id, 'type' => 'charge', 'amount' => 500,
            'idempotency_key' => 'bad', 'created_at' => now(),
        ]);
    }
}
