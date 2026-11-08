<?php

namespace Modules\Servers\Tests\Feature;

use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Modules\Clock\Tests\Concerns\FakesClock;
use Modules\Customers\Models\Customer;
use Modules\Network\Models\IpAddress;
use Modules\Network\Services\IpService;
use Modules\Servers\Classes\ServerSearch;
use Modules\Servers\Enums\ServerStatus;
use Modules\Servers\Livewire\ServerIndex;
use Modules\Servers\Livewire\ServerShow;
use Modules\Servers\Models\Server;
use Tests\TestCase;

class ServerPagesTest extends TestCase
{
    use FakesClock, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->setClock('2026-01-01 10:20:00');
    }

    private function login(StaffRole $role = StaffRole::Viewer): User
    {
        return tap(User::factory()->role($role)->create(), fn ($user) => $this->actingAs($user));
    }

    public function test_guests_are_sent_to_the_login(): void
    {
        $this->get('/servers')->assertRedirect('/login');
    }

    public function test_the_list_refuses_parameters_it_does_not_know(): void
    {
        $this->login();

        $this->get('/servers?q=web&colour=red')->assertStatus(422);
        $this->get('/servers?q=web&status=running&customer=1&sort=name&dir=asc&page=1')->assertOk();
    }

    public function test_the_list_refuses_values_it_does_not_understand(): void
    {
        $this->login();

        $this->get('/servers?status=banana')->assertStatus(422);
        $this->get('/servers?sort=password')->assertStatus(422);
        $this->get('/servers?page=0')->assertStatus(422);
    }

    public function test_the_search_state_comes_from_the_url_and_goes_back_to_it(): void
    {
        $this->login();
        Server::factory()->create(['name' => 'alpha-web']);
        Server::factory()->create(['name' => 'beta-db']);

        Livewire::withQueryParams(['q' => 'alpha'])
            ->test(ServerIndex::class)
            ->assertSet('q', 'alpha')
            ->assertSee('alpha-web')
            ->assertDontSee('beta-db')
            ->set('q', 'beta')
            ->assertSee('beta-db')
            ->assertDontSee('alpha-web')
            ->assertSeeHtml('data-state="ready"');
    }

    public function test_search_finds_by_customer_and_by_current_ip(): void
    {
        $this->login();
        IpAddress::factory()->create(['address' => '203.0.113.77']);
        $server = Server::factory()->running()->create(['name' => 'plain', 'customer_id' => Customer::factory()->create(['name' => 'Wayne Corp'])->id]);
        app(IpService::class)->addIp($server);
        Server::factory()->create(['name' => 'other']);

        Livewire::test(ServerIndex::class)->set('q', 'Wayne')->assertSee('plain')->assertDontSee('other');
        Livewire::test(ServerIndex::class)->set('q', '203.0.113.77')->assertSee('plain')->assertDontSee('other');
    }

    public function test_filter_by_status(): void
    {
        $this->login();
        Server::factory()->running()->create(['name' => 'up-one']);
        Server::factory()->create(['name' => 'waiting-one', 'status' => ServerStatus::Pending]);

        Livewire::test(ServerIndex::class)->set('status', 'running')->assertSee('up-one')->assertDontSee('waiting-one');
    }

    public function test_an_empty_list_says_so(): void
    {
        $this->login();

        Livewire::test(ServerIndex::class)->assertSeeHtml('data-state="empty"')->assertSee('There are no servers yet');
        Livewire::test(ServerIndex::class)->set('q', 'nothing like it')->assertSee('No server matches these filters');
    }

    public function test_a_failing_query_shows_an_error_not_an_empty_list(): void
    {
        $this->login();
        $this->app->bind(ServerSearch::class, fn () => new class extends ServerSearch
        {
            public function paginate(array $filters, int $perPage = 15): never
            {
                throw new \RuntimeException('database is gone');
            }
        });

        Livewire::test(ServerIndex::class)
            ->assertSeeHtml('data-state="error"')
            ->assertDontSeeHtml('data-state="empty"')
            ->assertSee('could not be loaded');
    }

    public function test_only_staff_who_manage_infrastructure_can_create_servers(): void
    {
        IpAddress::factory()->count(2)->create();
        $customer = Customer::factory()->create();

        $this->login(StaffRole::Viewer);
        Livewire::test(ServerIndex::class)->set('newCustomer', (string) $customer->id)->set('newName', 'web-1')->call('create')->assertForbidden();
        $this->assertSame(0, Server::count());

        $this->login(StaffRole::Support);
        Livewire::test(ServerIndex::class)->set('newCustomer', (string) $customer->id)->set('newName', 'web-1')->call('create')->assertHasNoErrors();
        $this->assertSame(1, Server::count());
    }

    public function test_only_staff_who_manage_infrastructure_can_terminate(): void
    {
        $server = Server::factory()->running('2026-01-01 10:20:00')->create();

        $this->login(StaffRole::Viewer);
        Livewire::test(ServerShow::class, ['server' => $server])->call('terminate')->assertForbidden();
        $this->assertSame(ServerStatus::Running, $server->fresh()->status);

        $this->login(StaffRole::Support);
        Livewire::test(ServerShow::class, ['server' => $server])->call('terminate');
        $this->assertSame(ServerStatus::Terminating, $server->fresh()->status);
    }

    public function test_the_server_page_renders_with_its_ips_and_billing_history(): void
    {
        $this->login();
        IpAddress::factory()->create(['address' => '203.0.113.5']);
        $server = Server::factory()->running('2026-01-01 10:20:00')->create(['name' => 'web-9']);
        app(IpService::class)->addIp($server);

        $this->get("/servers/{$server->id}")->assertOk()->assertSee('web-9')->assertSee('203.0.113.5');
    }
}
