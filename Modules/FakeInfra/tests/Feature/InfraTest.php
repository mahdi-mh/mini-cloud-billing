<?php

namespace Modules\FakeInfra\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Modules\FakeInfra\Models\InfraServer;
use Tests\TestCase;

class InfraTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // own database, own migrations
        Artisan::call('migrate:fresh', ['--database' => 'infra', '--path' => 'Modules/FakeInfra/database/infra', '--force' => true]);

        config(['fakeinfra.fail_rate' => 0, 'fakeinfra.lie_rate' => 0, 'fakeinfra.create_delay' => 4]);
    }

    public function test_a_new_server_takes_a_few_seconds(): void
    {
        $this->putJson('/infra/servers/tok-1', ['name' => 'web'])->assertCreated()->assertJsonPath('state', 'creating');
        $this->getJson('/infra/servers/tok-1')->assertJsonPath('state', 'creating');

        Carbon::setTestNow(now()->addSeconds(5));

        $this->getJson('/infra/servers/tok-1')->assertJsonPath('state', 'running');
    }

    public function test_creating_twice_with_the_same_token_gives_one_server(): void
    {
        $this->putJson('/infra/servers/tok-1')->assertCreated();
        $this->putJson('/infra/servers/tok-1')->assertOk();

        $this->assertSame(1, InfraServer::count());
    }

    public function test_delete_keeps_a_tombstone_and_is_repeatable(): void
    {
        $this->putJson('/infra/servers/tok-1');

        $this->deleteJson('/infra/servers/tok-1')->assertOk()->assertJsonPath('state', 'deleted');
        $this->deleteJson('/infra/servers/tok-1')->assertOk()->assertJsonPath('state', 'deleted');
        $this->getJson('/infra/servers/unknown')->assertNotFound();
    }

    public function test_failing_calls_do_nothing(): void
    {
        config(['fakeinfra.fail_rate' => 1]);

        $this->putJson('/infra/servers/tok-1')->assertStatus(503);

        $this->assertSame(0, InfraServer::count());
    }

    public function test_lying_calls_do_the_work_but_report_an_error(): void
    {
        config(['fakeinfra.lie_rate' => 1]);

        $this->putJson('/infra/servers/tok-1')->assertStatus(500);

        $this->assertSame(1, InfraServer::count());
    }

    public function test_reads_never_lie(): void
    {
        $this->putJson('/infra/servers/tok-1');
        config(['fakeinfra.lie_rate' => 1]);

        $this->getJson('/infra/servers/tok-1')->assertOk();
    }
}
