<?php

namespace Whilesmart\Integrations;

use Illuminate\Support\ServiceProvider;

class IntegrationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/integrations.php', 'integrations');


        $this->app->singleton('integrations', function ($app) {
            unset($app);// bypasses phpmd checks. Remove if $app is to be used
            return new IntegrationsManager();
        });
    }

    public function boot(): void
    {
        $this->publishConfig();
        $this->loadRoutes();
        $this->loadMigrations();
    }

    protected function publishConfig(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/integrations.php' => config_path('integrations.php'),
            ], 'integrations-config');
        }
    }

    protected function loadRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/integrations.php');
    }

    protected function loadMigrations(): void
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }
    }
}
