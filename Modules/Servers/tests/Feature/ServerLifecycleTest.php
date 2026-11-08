<?php

namespace Modules\Servers\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Clock\Services\Clock;
use Modules\Clock\Tests\Concerns\FakesClock;
use Modules\Customers\Enums\CustomerStatusType;
use Modules\Customers\Models\Customer;
use Modules\Network\Exceptions\NoFreeIpAddress;
use Modules\Network\Models\IpAddress;
use Modules\Network\Models\IpAssignment;
use Modules\Servers\Classes\ServerSyncer;
use Modules\Servers\Enums\ServerStatus;
use Modules\Servers\Exceptions\ServerNotAllowed;
use Modules\Servers\Jobs\SyncServer;
use Modules\Servers\Models\Server;
use Modules\Servers\Services\ServerService;
use Modules\Servers\Tests\Concerns\FakesInfra;
use Tests\TestCase;

class ServerLifecycleTest extends TestCase
{
    use FakesClock, FakesInfra, RefreshDatabase;

    private ServerService $servers;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->fakeInfra();
        $this->setClock('2026-01-01 10:20:00');
        config(['servers.sync_max_attempts' => 5]);

        IpAddress::factory()->count(3)->create();
        $this->servers = app(ServerService::class);
        $this->customer = Customer::factory()->create();
    }

    private function sync(Server $server): bool
    {
        return app(ServerSyncer::class)->sync($server->fresh());
    }

    public function test_creating_a_server_records_it_and_reserves_an_ip_without_touching_the_infra(): void
    {
        $server = $this->servers->create($this->customer, 'web-1');

        $this->assertSame(ServerStatus::Pending, $server->status);
        $this->assertSame(1, IpAssignment::where('server_id', $server->id)->whereNull('ended_at')->count());
        $this->assertSame(0, $this->infraCalls);
        Queue::assertPushed(SyncServer::class);
    }

    public function test_server_starts_billing_when_it_is_seen_running(): void
    {
        $server = $this->servers->create($this->customer, 'web-1');

        $this->assertFalse($this->sync($server)); // asked the infra to create it
        $this->assertFalse($this->sync($server)); // still creating
        $this->infra[$server->infra_token] = 'running';

        app(Clock::class)->advance(90);
        $this->assertTrue($this->sync($server));

        $server->refresh();
        $this->assertSame(ServerStatus::Running, $server->status);
        $this->assertSame('2026-01-01 10:21:30', $server->started_at->toDateTimeString());
    }

    public function test_an_infra_that_lies_about_creating_does_not_lose_or_duplicate_the_server(): void
    {
        $server = $this->servers->create($this->customer, 'web-1');

        // 1: GET -> unknown   2: PUT -> did it, but says 500
        $this->infraScript = ['ok', 'lie'];
        $this->sync($server);

        $this->assertSame([$server->infra_token => 'creating'], $this->infra);

        // next passes just find it and wait for it
        $this->infra[$server->infra_token] = 'running';
        $this->assertTrue($this->sync($server));

        $this->assertSame(ServerStatus::Running, $server->fresh()->status);
        $this->assertCount(1, $this->infra);
        $this->assertSame(1, Server::count());
    }

    public function test_a_create_that_really_failed_is_retried_with_the_same_token(): void
    {
        $server = $this->servers->create($this->customer, 'web-1');

        $this->infraScript = ['ok', 'fail']; // GET unknown, PUT fails and does nothing
        $this->sync($server);
        $this->assertSame([], $this->infra);

        $this->sync($server); // GET unknown again, PUT works
        $this->assertSame([$server->infra_token => 'creating'], $this->infra);
    }

    public function test_infra_errors_on_every_call_never_crash_a_pass(): void
    {
        $server = $this->servers->create($this->customer, 'web-1');
        $this->infraScript = array_fill(0, 3, 'fail');

        $this->assertFalse($this->sync($server));
        $this->assertSame(ServerStatus::Pending, $server->fresh()->status);
    }

    public function test_a_server_that_never_gets_ready_is_given_up_and_its_ip_is_freed(): void
    {
        $server = $this->servers->create($this->customer, 'web-1');

        for ($i = 0; $i < 5; $i++) {
            $this->sync($server);
        }

        $server->refresh();
        $this->assertSame(ServerStatus::Failed, $server->status);
        $this->assertNull($server->started_at);
        $this->assertSame(0, IpAssignment::whereNull('ended_at')->count());
        $this->assertSame('deleted', $this->infra[$server->infra_token]); // cleaned up on the infra side too
    }

    public function test_terminating_stops_billing_now_and_frees_the_ip(): void
    {
        $server = $this->servers->create($this->customer, 'web-1');
        $server->update(['status' => ServerStatus::Running, 'started_at' => '2026-01-01 10:20:00']);
        app(Clock::class)->advance(3600);

        $this->servers->terminate($server);

        $server->refresh();
        $this->assertSame(ServerStatus::Terminating, $server->status);
        $this->assertSame('2026-01-01 11:20:00', $server->ended_at->toDateTimeString());
        $this->assertSame(0, IpAssignment::whereNull('ended_at')->count());
    }

    public function test_terminating_survives_a_delete_that_lies(): void
    {
        $server = $this->servers->create($this->customer, 'web-1');
        $this->infra[$server->infra_token] = 'running';
        $server->update(['status' => ServerStatus::Running, 'started_at' => '2026-01-01 10:20:00']);
        $this->servers->terminate($server);

        $this->infraScript = ['ok', 'lie']; // GET running, DELETE done but reported as an error
        $this->assertFalse($this->sync($server));

        $this->assertTrue($this->sync($server)); // GET says deleted
        $this->assertSame(ServerStatus::Terminated, $server->fresh()->status);
    }

    public function test_terminating_twice_changes_nothing_the_second_time(): void
    {
        $server = $this->servers->create($this->customer, 'web-1');
        $server->update(['status' => ServerStatus::Running, 'started_at' => '2026-01-01 10:20:00']);

        $this->servers->terminate($server);
        $endedAt = $server->fresh()->ended_at;

        app(Clock::class)->advance(7200);
        $this->servers->terminate($server);

        $this->assertEquals($endedAt, $server->fresh()->ended_at);
    }

    public function test_a_suspended_customer_cannot_create_servers(): void
    {
        $this->customer->addStatus(CustomerStatusType::Suspended);

        $this->expectException(ServerNotAllowed::class);
        $this->servers->create($this->customer, 'web-1');
    }

    public function test_an_empty_pool_leaves_no_half_created_server(): void
    {
        IpAddress::query()->get()->each(fn () => $this->servers->create($this->customer, 'filler-'.rand()));
        $before = Server::count();

        try {
            $this->servers->create($this->customer, 'one-too-many');
            $this->fail('expected the pool to be empty');
        } catch (NoFreeIpAddress) {
            $this->assertSame($before, Server::count());
        }
    }
}
