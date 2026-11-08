<?php

namespace Modules\Billing\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Modules\Billing\Database\Seeders\PriceSeeder;
use Modules\Billing\Models\Charge;
use Modules\Clock\Tests\Concerns\FakesClock;
use Modules\Customers\Models\Customer;
use Modules\Network\Models\IpAddress;
use Modules\Network\Services\IpService;
use Modules\Servers\Models\Server;
use Modules\Wallet\Models\LedgerEntry;
use Tests\TestCase;

/**
 * The billing command as separate processes against the real database.
 */
class BillingRaceTest extends TestCase
{
    use DatabaseTruncation, FakesClock;

    private const SERVERS = 20;

    private const HOURS = 30; // 2026-01-01 10:00 .. 2026-01-02 15:00

    private int $expectedCharges;

    private int $expectedMoney;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->seed(PriceSeeder::class);
        $this->setClock('2026-01-01 10:20:00');
        IpAddress::factory()->count(self::SERVERS)->create();

        $ips = app(IpService::class);

        for ($i = 0; $i < self::SERVERS; $i++) {
            $server = Server::factory()->running('2026-01-01 10:20:00')->create(['customer_id' => Customer::factory()->create()->id]);
            $ips->addIp($server);
        }

        $this->setClock('2026-01-02 15:30:00');

        $this->expectedCharges = self::SERVERS * self::HOURS * 2;
        $this->expectedMoney = self::SERVERS * self::HOURS * (12 + 2);
    }

    protected function tearDown(): void
    {
        $this->truncateDatabaseTables();

        parent::tearDown();
    }

    private function assertEveryHourChargedOnce(): void
    {
        $this->assertSame($this->expectedCharges, Charge::count());

        // the same key twice is impossible, but count it anyway: no resource-hour is repeated
        $distinct = DB::table('charges')->distinct()->count(DB::raw("concat(billable_type, '-', billable_id, '-', hour_start)"));
        $this->assertSame($this->expectedCharges, $distinct);

        // every charge has exactly one money movement, and the movements add up
        $this->assertSame($this->expectedCharges, LedgerEntry::where('type', 'charge')->count());
        $this->assertSame(-$this->expectedMoney, (int) LedgerEntry::sum('amount'));
    }

    public function test_three_runs_at_the_same_moment_charge_every_hour_once(): void
    {
        Process::pool(function (Pool $pool) {
            foreach (range(1, 3) as $_) {
                $pool->path(base_path())->command(['php', 'artisan', 'billing:run']);
            }
        })->start()->wait();

        $this->assertEveryHourChargedOnce();
    }

    public function test_a_run_that_is_killed_halfway_leaves_nothing_broken_and_the_next_run_finishes(): void
    {
        $run = Process::path(base_path())->start(['php', 'artisan', 'billing:run']);

        // wait until it is really busy, then pull the plug
        $deadline = microtime(true) + 30;

        while (Charge::count() < 50 && microtime(true) < $deadline) {
            usleep(50_000);
        }

        $run->signal(SIGKILL);
        $run->wait();

        $partial = Charge::count();
        $this->assertGreaterThan(0, $partial);
        $this->assertLessThan($this->expectedCharges, $partial, 'the run finished before it was killed, make the data bigger');

        // no charge without its money movement, no money movement without its charge
        $this->assertSame($partial, LedgerEntry::where('type', 'charge')->count());

        Process::path(base_path())->run(['php', 'artisan', 'billing:run'])->throw();

        $this->assertEveryHourChargedOnce();
    }
}
