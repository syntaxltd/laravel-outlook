<?php

namespace Dytechltd\LaravelOutlook;

use Illuminate\Support\Facades\App;
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
            __DIR__ . '/../config/gmail.php' => config_path('gmail.php'),
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

        $this->app->bind('laravel-gmail', function ($app) {
            return new LaravelGmail($app['config']);
        });
    }
}
