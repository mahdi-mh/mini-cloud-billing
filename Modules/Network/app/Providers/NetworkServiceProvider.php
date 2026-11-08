<?php

namespace Modules\Network\Providers;

use Modules\Network\Console\MoveIpCommand;
use Modules\Network\Services\IpService;
use Modules\Servers\Interfaces\AllocatesIp;
use Livewire\Livewire;
use Modules\Network\Livewire\ServerIps;
use Nwidart\Modules\Support\ModuleServiceProvider;

class NetworkServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Network';

    protected string $nameLower = 'network';

    protected array $commands = [
        MoveIpCommand::class,
    ];

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->bind(AllocatesIp::class, IpService::class);
    }

    public function boot(): void
    {
        parent::boot();

        Livewire::component('network.server-ips', ServerIps::class);
    }
}
