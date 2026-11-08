<?php

namespace Modules\FakeInfra\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;
use Modules\FakeInfra\Http\Middleware\FlakyInfra;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'FakeInfra';

    /**
     * Called before routes are registered.
     *
     * Register any model bindings or pattern based filters.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Define the routes for the application.
     */
    public function map(): void
    {
        $this->mapWebRoutes();
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     */
    protected function mapWebRoutes(): void
    {
        // only the infra process exposes the fake API
        if (! config('fakeinfra.serve')) {
            return;
        }

        Route::middleware(['api', FlakyInfra::class])
            ->prefix('infra')
            ->group(module_path($this->name, '/routes/api.php'));
    }
}
