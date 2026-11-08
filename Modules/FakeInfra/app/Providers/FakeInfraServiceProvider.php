<?php

namespace Modules\FakeInfra\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class FakeInfraServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'FakeInfra';

    protected string $nameLower = 'fakeinfra';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        // the route provider boots before this provider does, and it needs the config
        $this->mergeConfigFrom(module_path($this->name, 'config/config.php'), $this->nameLower);

        parent::register();
    }
}
