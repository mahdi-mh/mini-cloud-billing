<?php

namespace Modules\Network\Tests\Feature;

use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Modules\Clock\Tests\Concerns\FakesClock;
use Modules\Network\Livewire\IpIndex;
use Modules\Network\Livewire\ServerIps;
use Modules\Network\Models\IpAddress;
use Modules\Network\Models\IpAssignment;
use Modules\Network\Services\IpService;
use Modules\Servers\Models\Server;
use Tests\TestCase;

class IpPagesTest extends TestCase
{
    use FakesClock, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->setClock('2026-01-01 10:20:00');
    }

    public function test_the_list_filters_free_and_used_and_rejects_unknown_input(): void
    {
        $this->actingAs(User::factory()->create());
        IpAddress::factory()->create(['address' => '10.0.0.1']);
        IpAddress::factory()->create(['address' => '10.0.0.2']);
        app(IpService::class)->addIp(Server::factory()->running()->create());

        Livewire::test(IpIndex::class)->set('state', 'free')->assertSee('10.0.0.2')->assertDontSee('10.0.0.1');
        Livewire::test(IpIndex::class)->set('state', 'used')->assertSee('10.0.0.1')->assertDontSee('10.0.0.2');

        $this->get('/ips?state=maybe')->assertStatus(422);
        $this->get('/ips?x=1')->assertStatus(422);
    }

    public function test_moving_an_address_from_the_server_page_follows_the_roles(): void
    {
        IpAddress::factory()->count(3)->create();
        $ips = app(IpService::class);
        $from = Server::factory()->running('2026-01-01 09:00:00')->create();
        $to = Server::factory()->running('2026-01-01 09:00:00')->create();
        $ips->addIp($from);
        $moving = $ips->addIp($from)->ipAddress;

        $this->actingAs(User::factory()->role(StaffRole::Viewer)->create());
        Livewire::test(ServerIps::class, ['server' => $from])->call('startMove', $moving->id)->assertForbidden();
        Livewire::test(ServerIps::class, ['server' => $from])->set('movingIp', $moving->id)->set('targetServer', (string) $to->id)->call('move')->assertForbidden();

        $this->actingAs(User::factory()->role(StaffRole::Support)->create());
        Livewire::test(ServerIps::class, ['server' => $from])->set('movingIp', $moving->id)->set('targetServer', (string) $to->id)->call('move')->assertHasNoErrors();

        $this->assertSame($to->id, IpAssignment::where('ip_address_id', $moving->id)->whereNull('ended_at')->value('server_id'));
    }

    public function test_a_stale_move_shows_an_error_instead_of_moving_twice(): void
    {
        IpAddress::factory()->count(3)->create();
        $ips = app(IpService::class);
        $from = Server::factory()->running('2026-01-01 09:00:00')->create();
        $first = Server::factory()->running('2026-01-01 09:00:00')->create();
        $second = Server::factory()->running('2026-01-01 09:00:00')->create();
        $ips->addIp($from);
        $moving = $ips->addIp($from)->ipAddress;
        $this->actingAs(User::factory()->role(StaffRole::Owner)->create());

        // two browser tabs showing the same server page
        $tabA = Livewire::test(ServerIps::class, ['server' => $from])->set('movingIp', $moving->id);
        $tabB = Livewire::test(ServerIps::class, ['server' => $from])->set('movingIp', $moving->id);

        $tabA->set('targetServer', (string) $first->id)->call('move')->assertHasNoErrors();
        $tabB->set('targetServer', (string) $second->id)->call('move')->assertHasErrors('targetServer');

        $this->assertSame($first->id, IpAssignment::where('ip_address_id', $moving->id)->whereNull('ended_at')->value('server_id'));
    }
}
