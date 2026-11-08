<?php

namespace Modules\Clock\Tests\Feature;

use App\Enums\StaffRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Clock\Livewire\Control;
use Modules\Clock\Services\Clock;
use Modules\Clock\Tests\Concerns\FakesClock;
use Tests\TestCase;

class ClockControlTest extends TestCase
{
    use FakesClock, RefreshDatabase;

    public function test_only_the_owner_moves_the_clock(): void
    {
        $this->setClock('2026-01-01 10:00:00');

        foreach ([StaffRole::Support, StaffRole::Viewer] as $role) {
            $this->actingAs(User::factory()->role($role)->create());
            Livewire::test(Control::class)->call('advance', 1)->assertForbidden();
        }

        $this->assertSame('2026-01-01 10:00:00', app(Clock::class)->now()->toDateTimeString());

        $this->actingAs(User::factory()->role(StaffRole::Owner)->create());
        Livewire::test(Control::class)->call('advance', 24);

        $this->assertSame('2026-01-02 10:00:00', app(Clock::class)->now()->toDateTimeString());
    }
}
