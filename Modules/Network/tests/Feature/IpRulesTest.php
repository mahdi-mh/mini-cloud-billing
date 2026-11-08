<?php

namespace Modules\Network\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Clock\Services\Clock;
use Modules\Clock\Tests\Concerns\FakesClock;
use Modules\Customers\Models\Customer;
use Modules\Network\Exceptions\IpMoveRefused;
use Modules\Network\Exceptions\NoFreeIpAddress;
use Modules\Network\Models\IpAddress;
use Modules\Network\Models\IpAssignment;
use Modules\Network\Services\IpService;
use Modules\Servers\Enums\ServerStatus;
use Modules\Servers\Models\Server;
use Tests\TestCase;

class IpRulesTest extends TestCase
{
    use FakesClock, RefreshDatabase;

    private IpService $ips;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->setClock('2026-01-01 10:00:00');
        $this->ips = app(IpService::class);
    }

    private function server(?Customer $customer = null): Server
    {
        return Server::factory()->running()->create(['customer_id' => ($customer ?? Customer::factory()->create())->id, 'started_at' => '2026-01-01 09:00:00']);
    }

    /** a server holding two addresses, so one of them may leave */
    private function serverWithTwoIps(?Customer $customer = null): array
    {
        $server = $this->server($customer);

        return [$server, $this->ips->addIp($server)->ipAddress, $this->ips->addIp($server)->ipAddress];
    }

    public function test_every_address_goes_to_one_server_only_and_the_pool_runs_out(): void
    {
        IpAddress::factory()->count(2)->create();

        $a = $this->ips->addIp($this->server());
        $b = $this->ips->addIp($this->server());

        $this->assertNotSame($a->ip_address_id, $b->ip_address_id);

        $this->expectException(NoFreeIpAddress::class);
        $this->ips->addIp($this->server());
    }

    public function test_moving_to_another_customers_server_closes_and_opens_at_the_same_instant(): void
    {
        IpAddress::factory()->count(3)->create();
        [$from, $keep, $ip] = $this->serverWithTwoIps();
        $to = $this->server();

        app(Clock::class)->advance(1800);
        $this->ips->move($ip, $from, $to);

        $history = IpAssignment::where('ip_address_id', $ip->id)->orderBy('id')->get();
        $this->assertCount(2, $history);
        $this->assertSame($from->id, $history[0]->server_id);
        $this->assertSame($to->id, $history[1]->server_id);
        $this->assertEquals($history[0]->ended_at, $history[1]->started_at);
        $this->assertNull($history[1]->ended_at);
    }

    public function test_a_second_move_that_still_thinks_the_address_is_on_the_old_server_is_refused(): void
    {
        IpAddress::factory()->count(3)->create();
        [$from, $keep, $ip] = $this->serverWithTwoIps();
        $first = $this->server();
        $second = $this->server();

        $this->ips->move($ip, $from, $first);

        $this->expectException(IpMoveRefused::class);
        $this->ips->move($ip, $from, $second);
    }

    public function test_a_server_keeps_at_least_one_address(): void
    {
        IpAddress::factory()->count(2)->create();
        $from = $this->server();
        $ip = $this->ips->addIp($from)->ipAddress;

        $this->expectException(IpMoveRefused::class);
        $this->ips->move($ip, $from, $this->server());
    }

    public function test_an_address_cannot_go_to_a_server_that_is_going_away(): void
    {
        IpAddress::factory()->count(3)->create();
        [$from, $keep, $ip] = $this->serverWithTwoIps();
        $to = $this->server();
        $to->update(['status' => ServerStatus::Terminating, 'ended_at' => '2026-01-01 10:00:00']);

        $this->expectException(IpMoveRefused::class);
        $this->ips->move($ip, $from, $to);
    }

    public function test_the_database_refuses_a_second_open_assignment_of_the_same_address(): void
    {
        IpAddress::factory()->create();
        $first = $this->server();
        $second = $this->server();
        $assignment = $this->ips->addIp($first);

        $this->expectException(UniqueConstraintViolationException::class);
        IpAssignment::create(['ip_address_id' => $assignment->ip_address_id, 'server_id' => $second->id, 'started_at' => '2026-01-01 10:00:00']);
    }

    public function test_the_database_allows_the_address_again_once_the_first_assignment_is_closed(): void
    {
        IpAddress::factory()->create();
        $assignment = $this->ips->addIp($this->server());
        $assignment->update(['ended_at' => '2026-01-01 10:30:00']);

        IpAssignment::create(['ip_address_id' => $assignment->ip_address_id, 'server_id' => $this->server()->id, 'started_at' => '2026-01-01 10:30:00']);

        $this->assertSame(1, IpAssignment::whereNull('ended_at')->count());
    }

    public function test_the_database_refuses_a_period_that_ends_before_it_starts(): void
    {
        IpAddress::factory()->create();
        $assignment = $this->ips->addIp($this->server());

        $this->expectException(QueryException::class);
        $assignment->update(['ended_at' => '2025-12-31 00:00:00']);
    }
}
