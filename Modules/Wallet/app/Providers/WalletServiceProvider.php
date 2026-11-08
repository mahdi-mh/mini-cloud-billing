<?php

namespace Modules\Wallet\Providers;

use Livewire\Livewire;
use Modules\Wallet\Livewire\CustomerBalance;
use Modules\Wallet\Livewire\CustomerLedger;
use Nwidart\Modules\Support\ModuleServiceProvider;

class WalletServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Wallet';

    protected string $nameLower = 'wallet';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        Livewire::component('wallet.customer-balance', CustomerBalance::class);
        Livewire::component('wallet.customer-ledger', CustomerLedger::class);
    }
}
