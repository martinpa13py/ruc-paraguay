<?php

namespace martinpa13py\RUCParaguay;

use Illuminate\Support\ServiceProvider;
use martinpa13py\RUCParaguay\Console\Commands\RucParaguayCmdSearch;
use martinpa13py\RUCParaguay\Console\Commands\RucParaguayCmdUpdate;
use martinpa13py\RUCParaguay\Services\RUCParaguay;
use martinpa13py\RUCParaguay\Services\RUCParaguayUpdater;

class RUCParaguayServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->commands([
            RucParaguayCmdUpdate::class,
            RucParaguayCmdSearch::class,
        ]);

        $this->loadMigrationsFrom(__DIR__ . '/database/migrations/');
    }

    public function register(): void
    {
        $this->app->bind('RUCParaguay', function () {
            return new RUCParaguay();
        });

        $this->app->singleton(RUCParaguayUpdater::class, function () {
            return new RUCParaguayUpdater();
        });
    }
}