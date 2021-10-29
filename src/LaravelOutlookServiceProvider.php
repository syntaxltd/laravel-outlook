<?php

namespace Syntax\LaravelOutlook;

use Illuminate\Support\ServiceProvider;

class LaravelOutlookServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/azure.php' => config_path('azure.php'),
        ]);
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('laravel-outlook', function ($app) {
            return new LaravelOutlook();
        });
    }
}
