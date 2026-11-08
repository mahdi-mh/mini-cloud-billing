<?php

namespace Modules\Wallet\Tests\Feature;

use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Clock\Tests\Concerns\FakesClock;
use Modules\Customers\Models\Customer;
use Modules\Wallet\Livewire\CustomerBalance;
use Modules\Wallet\Livewire\CustomerLedger;
use Modules\Wallet\Models\LedgerEntry;
use Modules\Wallet\Services\WalletService;
use Tests\TestCase;

class CustomerWalletPageTest extends TestCase
{
    use FakesClock, RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setClock('2026-01-01 10:20:00');
        $this->customer = Customer::factory()->create();
    }

    private function login(StaffRole $role): void
    {
        $this->actingAs(User::factory()->role($role)->create());
    }

    public function test_owner_can_top_up_and_double_submit_adds_money_once(): void
    {
        $this->login(StaffRole::Owner);

        $page = Livewire::test(CustomerBalance::class, ['customer' => $this->customer])
            ->set('topUpAmount', '10.50')
            ->call('topUp')
            ->assertHasNoErrors();

        $this->assertSame(1050, app(WalletService::class)->balance($this->customer));

        // the same form submitted again with the same key (retry, double click, lost response)
        $key = LedgerEntry::first()->idempotency_key;
        app(WalletService::class)->topUp($this->customer, 1050, $key);

        $this->assertSame(1050, app(WalletService::class)->balance($this->customer));
        $this->assertSame(1, LedgerEntry::count());
    }

    public function test_two_clicks_on_the_same_form_are_one_top_up(): void
    {
        $this->login(StaffRole::Owner);

        $page = Livewire::test(CustomerBalance::class, ['customer' => $this->customer])->set('topUpAmount', '5');
        $key = $page->get('topUpKey');

        // the browser sends the same form state twice
        $page->call('topUp');
        $page->set('topUpKey', $key)->set('topUpAmount', '5')->call('topUp');

        $this->assertSame(500, app(WalletService::class)->balance($this->customer));
    }

    public function test_support_and_viewer_cannot_touch_money(): void
    {
        foreach ([StaffRole::Support, StaffRole::Viewer] as $role) {
            $this->login($role);

            Livewire::test(CustomerBalance::class, ['customer' => $this->customer])->set('topUpAmount', '10')->call('topUp')->assertForbidden();
            Livewire::test(CustomerBalance::class, ['customer' => $this->customer])->set('refundAmount', '10')->call('refund')->assertForbidden();
        }

        $this->assertSame(0, LedgerEntry::count());
    }

    public function test_bad_amounts_are_refused(): void
    {
        $this->login(StaffRole::Owner);

        foreach (['', 'abc', '-5', '0', '1.234', '1e3'] as $bad) {
            Livewire::test(CustomerBalance::class, ['customer' => $this->customer])->set('topUpAmount', $bad)->call('topUp')->assertHasErrors('topUpAmount');
        }

        $this->assertSame(0, LedgerEntry::count());
    }

    public function test_the_ledger_shows_a_loading_state_before_it_has_loaded(): void
    {
        $this->login(StaffRole::Viewer);

        Livewire::test(CustomerLedger::class, ['customer' => $this->customer])
            ->assertSeeHtml('data-state="loading"')
            ->assertDontSeeHtml('data-state="empty"');
    }

    public function test_the_ledger_shows_every_movement_or_says_there_are_none(): void
    {
        Livewire::withoutLazyLoading();
        $this->login(StaffRole::Viewer);

        Livewire::test(CustomerLedger::class, ['customer' => $this->customer])->assertSeeHtml('data-state="empty"');

        app(WalletService::class)->topUp($this->customer, 2500, 'a', 'Top-up by staff');
        app(WalletService::class)->charge($this->customer->id, 12, 'b', 'server #1 2026-01-01 10:00');

        Livewire::withoutLazyLoading();
        Livewire::test(CustomerLedger::class, ['customer' => $this->customer])
            ->assertSeeHtml('data-state="ready"')
            ->assertSee('Top-up by staff')
            ->assertSee('server #1 2026-01-01 10:00');
    }
}
