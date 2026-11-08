<?php

namespace Modules\Billing\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Livewire\Livewire;
use Modules\Billing\Console\ChangePriceCommand;
use Modules\Billing\Console\RunBillingCommand;
use Modules\Billing\Livewire\ServerCharges;
use Nwidart\Modules\Support\ModuleServiceProvider;

class BillingServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Billing';

    protected string $nameLower = 'billing';

    protected array $commands = [
        RunBillingCommand::class,
        ChangePriceCommand::class,
    ];

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    protected function configureSchedules(Schedule $schedule): void
    {
        // no overlap guard on purpose: running twice is safe, a stuck lock would not be
        $schedule->command('billing:run')->everyMinute();
    }

    public function boot(): void
    {
        parent::boot();

        Livewire::component('billing.server-charges', ServerCharges::class);
    }
}
