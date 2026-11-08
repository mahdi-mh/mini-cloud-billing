<?php

namespace Modules\Network\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Process;
use Illuminate\Process\Pool;
use Modules\Clock\Tests\Concerns\FakesClock;
use Modules\Customers\Models\Customer;
use Modules\Network\Models\IpAddress;
use Modules\Network\Models\IpAssignment;
use Modules\Network\Services\IpService;
use Modules\Servers\Models\Server;
use Tests\TestCase;

/**
 * Real processes against the real database: two requests for the same address at the same time.
 */
class IpRaceTest extends TestCase
{
    use DatabaseTruncation, FakesClock;

    private Server $from;

    private Server $toA;

    private Server $toB;

    private IpAddress $ip;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setClock('2026-01-01 10:00:00');
        IpAddress::factory()->count(3)->create();

        $make = fn () => Server::factory()->running()->create([
            'customer_id' => Customer::factory()->create()->id,
            'started_at' => '2026-01-01 09:00:00',
        ]);

        $this->from = $make();
        $this->toA = $make();
        $this->toB = $make();

        $ips = app(IpService::class);
        $ips->addIp($this->from);
        $this->ip = $ips->addIp($this->from)->ipAddress;
    }

    protected function tearDown(): void
    {
        // this data was really committed, don't leave it for the next test class
        $this->truncateDatabaseTables();

        parent::tearDown();
    }

    private function move(Server $to): array
    {
        return ['php', 'artisan', 'ips:move', $this->ip->id, $this->from->id, $to->id];
    }

    private function assertOneOpenAssignmentOnAWinner(array $results): void
    {
        $succeeded = collect($results)->filter(fn ($r) => $r->successful())->count();

        $this->assertSame(1, $succeeded, 'exactly one of the two moves may succeed');

        $open = IpAssignment::where('ip_address_id', $this->ip->id)->whereNull('ended_at')->get();
        $this->assertCount(1, $open);
        $this->assertContains($open->first()->server_id, [$this->toA->id, $this->toB->id]);
    }

    public function test_two_moves_at_the_same_moment_only_one_wins(): void
    {
        $results = Process::pool(function (Pool $pool) {
            $pool->path(base_path())->command($this->move($this->toA));
            $pool->path(base_path())->command($this->move($this->toB));
        })->start()->wait();

        $this->assertOneOpenAssignmentOnAWinner($results->collect()->all());
    }

    public function test_a_move_that_arrives_while_another_is_still_open_is_refused(): void
    {
        // the first move is done but its transaction stays open for a while, like a slow request
        $slow = Process::path(base_path())->start([
            'php', 'artisan', 'tinker', '--execute='
            .'Illuminate\Support\Facades\DB::transaction(function () {'
            .'app(Modules\Network\Services\IpService::class)->move('
            ."Modules\\Network\\Models\\IpAddress::find({$this->ip->id}),"
            ."Modules\\Servers\\Models\\Server::find({$this->from->id}),"
            ."Modules\\Servers\\Models\\Server::find({$this->toA->id}));"
            .'sleep(3); });',
        ]);

        usleep(1_500_000);

        $fast = Process::path(base_path())->run($this->move($this->toB));
        $slowResult = $slow->wait();

        $this->assertOneOpenAssignmentOnAWinner([$slowResult, $fast]);
        $this->assertSame($this->toA->id, IpAssignment::where('ip_address_id', $this->ip->id)->whereNull('ended_at')->value('server_id'));
    }
}
