<?php

namespace Modules\Servers\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Clock\Tests\Concerns\FakesClock;
use Modules\Network\Models\IpAddress;
use Modules\Network\Models\IpAssignment;
use Modules\Network\Services\IpService;
use Modules\Servers\Classes\ServerReconciler;
use Modules\Servers\Enums\ServerStatus;
use Modules\Servers\Models\Server;
use Modules\Servers\Tests\Concerns\FakesInfra;
use Tests\TestCase;

class DriftTest extends TestCase
{
    use FakesClock, FakesInfra, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->fakeInfra();
        $this->setClock('2026-01-01 12:00:00');
    }

    public function test_something_running_on_the_infra_that_we_never_asked_for_is_removed(): void
    {
        $this->infra['ghost-token'] = 'running';

        $fixed = app(ServerReconciler::class)->reconcile();

        $this->assertSame('deleted', $this->infra['ghost-token']);
        $this->assertCount(1, $fixed);
    }

    public function test_a_server_we_bill_that_the_infra_lost_is_closed_and_its_ips_freed(): void
    {
        IpAddress::factory()->create();
        $server = Server::factory()->running('2026-01-01 10:00:00')->create();
        app(IpService::class)->addIp($server);

        app(ServerReconciler::class)->reconcile(); // the infra has never heard of it

        $server->refresh();
        $this->assertSame(ServerStatus::Terminated, $server->status);
        $this->assertSame('2026-01-01 12:00:00', $server->ended_at->toDateTimeString());
        $this->assertSame(0, IpAssignment::whereNull('ended_at')->count());
    }

    public function test_a_server_we_closed_but_that_still_runs_on_the_infra_is_deleted_there(): void
    {
        $server = Server::factory()->create(['status' => ServerStatus::Terminated, 'ended_at' => '2026-01-01 11:00:00']);
        $this->infra[$server->infra_token] = 'running';

        app(ServerReconciler::class)->reconcile();

        $this->assertSame('deleted', $this->infra[$server->infra_token]);
    }

    public function test_healthy_servers_and_servers_still_being_created_are_left_alone(): void
    {
        $running = Server::factory()->running('2026-01-01 10:00:00')->create();
        $pending = Server::factory()->create(['status' => ServerStatus::Pending]);
        $this->infra[$running->infra_token] = 'running';

        $fixed = app(ServerReconciler::class)->reconcile();

        $this->assertSame([], $fixed);
        $this->assertSame(ServerStatus::Running, $running->fresh()->status);
        $this->assertSame(ServerStatus::Pending, $pending->fresh()->status);
    }
}
