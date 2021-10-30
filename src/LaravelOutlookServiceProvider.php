<?php

namespace Syntax\LaravelOutlook;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Safe\Exceptions\DatetimeException;
use function Safe\date;

class LaravelOutlookServiceProvider extends ServiceProvider
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
            __DIR__ . '/../config/azure.php' => config_path('azure.php'),
        ]);

        if ($this->app->runningInConsole()) {
            // Export the migration
            if (!class_exists('CreateSocialAccessTokensTable')) {
                $this->publishes([
                    __DIR__ . '/../database/migrations/create_social_access_tokens_table.php.stub' => database_path('migrations/' . date('Y_m_d_His', time()) . '_create_social_access_tokens.php'),
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
        $this->app->bind('laravel-outlook', function ($app) {
            return new LaravelOutlook();
        });
        $this->mapWebRoutes();
    }

    /**
     * Define the "web" routes for the package.
     *
     * These routes all receive session state, CSRF protection, etc.
     *
     * @return void
     */
    protected function mapWebRoutes()
    {
        Route::middleware('web')
            ->namespace($this->namespace)
            ->group(__DIR__ . '/../routes/web.php');
    }
}
