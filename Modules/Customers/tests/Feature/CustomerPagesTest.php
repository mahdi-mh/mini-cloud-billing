<?php

namespace Modules\Customers\Tests\Feature;

use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Clock\Tests\Concerns\FakesClock;
use Modules\Customers\Enums\CustomerStatusType;
use Modules\Customers\Livewire\CustomerShow;
use Modules\Customers\Models\Customer;
use Tests\TestCase;

class CustomerPagesTest extends TestCase
{
    use FakesClock, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setClock('2026-01-01 10:20:00');
    }

    public function test_a_customer_can_be_verified_and_suspended_at_the_same_time(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs(User::factory()->role(StaffRole::Support)->create());

        Livewire::test(CustomerShow::class, ['customer' => $customer])->call('toggle', 'verified')->call('toggle', 'suspended');

        $this->assertTrue($customer->hasStatus(CustomerStatusType::Verified));
        $this->assertTrue($customer->hasStatus(CustomerStatusType::Suspended));

        $this->get("/customers/{$customer->id}")->assertOk()->assertSee('Verified')->assertSee('Suspended');
    }

    public function test_viewers_cannot_change_statuses(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs(User::factory()->role(StaffRole::Viewer)->create());

        Livewire::test(CustomerShow::class, ['customer' => $customer])->call('toggle', 'suspended')->assertForbidden();

        $this->assertFalse($customer->hasStatus(CustomerStatusType::Suspended));
    }

    public function test_the_customer_page_shows_balance_servers_and_history_areas(): void
    {
        $customer = Customer::factory()->create(['name' => 'Acme']);
        $this->actingAs(User::factory()->role(StaffRole::Owner)->create());

        $this->get("/customers/{$customer->id}")->assertOk()->assertSee('Acme')->assertSee('Balance')->assertSee('Servers')->assertSee('Money history');
    }

    public function test_customer_list_rejects_unknown_parameters_and_filters_by_search(): void
    {
        $this->actingAs(User::factory()->create());
        Customer::factory()->create(['name' => 'Wayne Corp']);
        Customer::factory()->create(['name' => 'Stark Industries']);

        $this->get('/customers?foo=1')->assertStatus(422);
        $this->get('/customers?q=Wayne')->assertOk()->assertSee('Wayne Corp')->assertDontSee('Stark Industries');
    }
}
