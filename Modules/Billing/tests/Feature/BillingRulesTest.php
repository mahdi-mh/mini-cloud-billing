<?php

namespace Modules\Billing\Tests\Feature;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Billing\Classes\BillingRunner;
use Modules\Billing\Database\Seeders\PriceSeeder;
use Modules\Billing\Enums\BillableType;
use Modules\Billing\Models\Charge;
use Modules\Billing\Services\PriceList;
use Modules\Clock\Services\Clock;
use Modules\Clock\Tests\Concerns\FakesClock;
use Modules\Customers\Models\Customer;
use Modules\Network\Models\IpAddress;
use Modules\Network\Services\IpService;
use Modules\Servers\Enums\ServerStatus;
use Modules\Servers\Models\Server;
use Modules\Wallet\Models\LedgerEntry;
use Modules\Wallet\Services\WalletService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BillingRulesTest extends TestCase
{
    use FakesClock, RefreshDatabase;

    private const SERVER_PRICE = 12;

    private const IP_PRICE = 2;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->seed(PriceSeeder::class);
        $this->setClock('2026-01-01 10:20:00');
    }

    private function runBilling()
    {
        return app(BillingRunner::class)->run();
    }

    private function runningServer(?Customer $customer = null, string $startedAt = '2026-01-01 10:20:00'): Server
    {
        return Server::factory()->running($startedAt)->create(['customer_id' => ($customer ?? Customer::factory()->create())->id]);
    }

    private function hoursCharged(Server $server): array
    {
        return Charge::where('billable_type', 'server')->where('billable_id', $server->id)
            ->orderBy('hour_start')->pluck('hour_start')->map->format('Y-m-d H')->all();
    }

    public function test_the_hour_we_are_in_is_charged_in_full_and_only_once(): void
    {
        $server = $this->runningServer();

        $first = $this->runBilling();
        $again = $this->runBilling();

        $this->assertSame(1, $first->charged);
        $this->assertSame(0, $again->charged);
        $this->assertSame(['2026-01-01 10'], $this->hoursCharged($server));
        $this->assertSame(-self::SERVER_PRICE, app(WalletService::class)->balance($server->customer_id));
    }

    public function test_every_new_hour_adds_exactly_one_charge(): void
    {
        $server = $this->runningServer();
        $this->runBilling();

        app(Clock::class)->advance(39 * 60); // 10:59
        $this->runBilling();
        app(Clock::class)->advance(60);      // 11:00
        $this->runBilling();
        $this->runBilling();

        $this->assertSame(['2026-01-01 10', '2026-01-01 11'], $this->hoursCharged($server));
    }

    public function test_a_stopped_billing_job_catches_up_with_every_missed_hour_once(): void
    {
        $server = $this->runningServer();
        $this->runBilling();

        // nobody ran the job for two days and a bit
        app(Clock::class)->advance(50 * 3600);

        $this->assertSame(50, $this->runBilling()->charged);
        $this->assertSame(0, $this->runBilling()->charged);

        $hours = $this->hoursCharged($server);
        $this->assertCount(51, $hours);
        $this->assertCount(51, array_unique($hours));
        $this->assertSame('2026-01-03 12', end($hours)); // no gaps: 10:00 on the 1st up to 12:00 on the 3rd
    }

    #[DataProvider('endings')]
    public function test_billing_stops_with_the_end_of_usage(string $endedAt, int $expectedHours): void
    {
        $server = $this->runningServer();
        $server->update(['status' => ServerStatus::Terminated, 'ended_at' => $endedAt]);
        app(Clock::class)->advance(24 * 3600);

        $this->runBilling();

        $this->assertCount($expectedHours, $this->hoursCharged($server));
    }

    public static function endings(): array
    {
        return [
            'ended in the same hour' => ['2026-01-01 10:40:00', 1],
            'ended exactly on the hour' => ['2026-01-01 11:00:00', 1],
            'one second into the next hour' => ['2026-01-01 11:00:01', 2],
        ];
    }

    public function test_a_server_that_never_ran_is_never_billed(): void
    {
        Server::factory()->create(['status' => ServerStatus::Pending]);
        Server::factory()->create(['status' => ServerStatus::Failed]);
        app(Clock::class)->advance(5 * 3600);

        $this->assertSame(0, $this->runBilling()->charged);
    }

    public function test_an_ip_on_a_server_is_billed_as_well(): void
    {
        IpAddress::factory()->create();
        $server = $this->runningServer();
        app(IpService::class)->addIp($server);

        $this->runBilling();

        $this->assertSame(1, Charge::where('billable_type', 'ip')->count());
        $this->assertSame(-(self::SERVER_PRICE + self::IP_PRICE), app(WalletService::class)->balance($server->customer_id));
    }

    public function test_an_ip_that_moves_mid_hour_is_paid_by_one_customer_in_every_hour(): void
    {
        IpAddress::factory()->count(2)->create();
        $ips = app(IpService::class);
        $a = $this->runningServer(startedAt: '2026-01-01 10:00:00');
        $b = $this->runningServer(startedAt: '2026-01-01 10:00:00');
        $moving = $ips->addIp($a)->ipAddress;
        $ips->addIp($a);

        $this->runBilling();
        app(Clock::class)->advance(10 * 60); // 10:30
        $ips->move($moving, $a, $b);
        app(Clock::class)->advance(3 * 3600);
        $this->runBilling();
        $this->runBilling();

        $charges = Charge::where('billable_type', 'ip')->where('billable_id', $moving->id)->orderBy('hour_start')->get();

        // 10:00 -> A (had it at the start of that hour); 11:00, 12:00, 13:00 -> B
        $this->assertSame(
            ['2026-01-01 10' => $a->customer_id, '2026-01-01 11' => $b->customer_id, '2026-01-01 12' => $b->customer_id, '2026-01-01 13' => $b->customer_id],
            $charges->mapWithKeys(fn ($c) => [$c->hour_start->format('Y-m-d H') => $c->customer_id])->all(),
        );
    }

    public function test_the_database_refuses_two_payers_for_the_same_ip_in_the_same_hour(): void
    {
        $hour = '2026-01-01 10:00:00';
        Charge::factory()->create(['billable_type' => BillableType::Ip, 'billable_id' => 7, 'hour_start' => $hour]);

        $this->expectException(UniqueConstraintViolationException::class);
        Charge::factory()->create(['billable_type' => BillableType::Ip, 'billable_id' => 7, 'hour_start' => $hour]);
    }

    public function test_a_new_price_starts_with_the_next_hour_and_never_touches_charged_hours(): void
    {
        $server = $this->runningServer();
        $this->runBilling(); // 10:00 charged at the old price

        app(PriceList::class)->change(BillableType::Server, 30);
        app(Clock::class)->advance(3600); // 11:20
        $this->runBilling();

        $amounts = Charge::where('billable_type', 'server')->orderBy('hour_start')->pluck('amount')->all();
        $this->assertSame([self::SERVER_PRICE, 30], $amounts);
    }

    public function test_one_failing_item_does_not_stop_the_others_and_leaves_nothing_half_written(): void
    {
        $good = $this->runningServer();
        $bad = $this->runningServer();
        $alsoGood = $this->runningServer();

        $broken = true;
        LedgerEntry::creating(function (LedgerEntry $entry) use (&$broken, $bad) {
            if ($broken && $entry->customer_id === $bad->customer_id) {
                throw new \RuntimeException('disk on fire');
            }
        });

        $report = $this->runBilling();

        $this->assertSame(2, $report->charged);
        $this->assertCount(1, $report->failures);
        $this->assertStringContainsString("server #{$bad->id}", $report->failures[0]);
        // the charge row of the failed item was rolled back together with its money movement
        $this->assertSame(2, Charge::count());
        $this->assertSame(2, LedgerEntry::count());

        // once the fault is gone the next run picks it up
        $broken = false;
        $this->assertSame(1, $this->runBilling()->charged);
        $this->assertSame(3, Charge::count());
        $this->assertSame(3, LedgerEntry::count());
    }
}
