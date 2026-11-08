<?php

namespace Modules\Servers\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Livewire\Livewire;
use Modules\Servers\Console\ReconcileServersCommand;
use Modules\Servers\Console\SyncServersCommand;
use Modules\Servers\Livewire\CustomerServers;
use Nwidart\Modules\Support\ModuleServiceProvider;

class ServersServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Servers';

    protected string $nameLower = 'servers';

    protected array $commands = [
        SyncServersCommand::class,
        ReconcileServersCommand::class,
    ];

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->command('servers:sync')->everyMinute();
        $schedule->command('servers:reconcile')->everyFiveMinutes();
    }

    public function boot(): void
    {
        parent::boot();

        Livewire::component('servers.customer-servers', CustomerServers::class);
    }
}
