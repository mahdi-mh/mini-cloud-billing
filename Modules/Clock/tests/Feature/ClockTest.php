<?php

namespace Modules\Clock\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Clock\Services\Clock;
use Modules\Clock\Tests\Concerns\FakesClock;
use Tests\TestCase;

class ClockTest extends TestCase
{
    use FakesClock, RefreshDatabase;

    public function test_time_only_moves_when_advanced(): void
    {
        $clock = app(Clock::class);
        $this->setClock('2026-01-01 10:20:30');

        $this->assertSame('2026-01-01 10:20:30', $clock->now()->toDateTimeString());

        $clock->advance(3 * 3600);

        $this->assertSame('2026-01-01 13:20:30', $clock->now()->toDateTimeString());
        $this->assertSame('2026-01-01 13:00:00', $clock->currentHour()->toDateTimeString());
    }

    public function test_clock_cannot_go_back(): void
    {
        $clock = app(Clock::class);
        $this->setClock('2026-01-01 10:00:00');

        $this->expectException(\InvalidArgumentException::class);
        $clock->set(Carbon::parse('2026-01-01 09:00:00', 'UTC'));
    }

    public function test_advance_command(): void
    {
        $this->setClock('2026-01-01 10:00:00');

        $this->artisan('clock:advance', ['amount' => '2 hours'])->assertSuccessful();

        $this->assertSame('2026-01-01 12:00:00', app(Clock::class)->now()->toDateTimeString());
    }
}
