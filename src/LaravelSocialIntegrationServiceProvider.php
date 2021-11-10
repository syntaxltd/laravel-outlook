<?php

namespace Syntax\LaravelSocialIntegration;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Safe\Exceptions\DatetimeException;
use function Safe\date;

class LaravelSocialIntegrationServiceProvider extends ServiceProvider
{
    /**
     * This namespace is applied to your controller routes.
     *
     * In addition, it is set as the URL generator's root namespace.
     *
     * @var string
     */
    protected string $namespace = 'Syntax\Http\Controller;s';

    /**
     * Bootstrap any package services.
     *
     * @return void
     * @throws DatetimeException
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/laravel-social-integration.php' => config_path('laravel-social-integration.php'),
        ]);

        if ($this->app->runningInConsole()) {
            // Export the migration
            if (!class_exists('CreateSocialAccessTokensTable')) {
                $this->publishes([
                    __DIR__ . '/../database/migrations/create_social_access_tokens_table.php.stub' => database_path('migrations' . date('Y_m_d_His', time()) . '_create_social_access_tokens.php'),
                ], 'migrations ');
            }
        }
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('laravel-social', function ($app, $client) {
            return LaravelSocialIntegration::service($client['client']);
        });
        $this->registerRoutes();
    }

    /**
     * Define the "web" routes for the package.
     *
     * These routes all receive session state, CSRF protection, etc.
     *
     * @return void
     */
    protected function registerRoutes()
    {
        Route::group($this->routeConfiguration(), function () {
            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        });
    }

    protected function routeConfiguration(): array
    {
        return [
            'prefix' => config('laravel-social-integration.routes.prefix'),
            'middleware' => config('laravel-social-integration.routes.middleware'),
        ];
    }
}
