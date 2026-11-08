<?php

namespace Modules\Clock\Providers;

use Livewire\Livewire;
use Modules\Clock\Console\AdvanceClockCommand;
use Modules\Clock\Console\SetClockCommand;
use Modules\Clock\Livewire\Control;
use Nwidart\Modules\Support\ModuleServiceProvider;

class ClockServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Clock';

    protected string $nameLower = 'clock';

    protected array $commands = [
        AdvanceClockCommand::class,
        SetClockCommand::class,
    ];

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        Livewire::component('clock.control', Control::class);
    }
}
