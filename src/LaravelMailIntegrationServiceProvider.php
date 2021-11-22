<?php

namespace Syntax\LaravelMailIntegration;

use Illuminate\Support\ServiceProvider;
use Safe\Exceptions\DatetimeException;
use function Safe\date;

class LaravelMailIntegrationServiceProvider extends ServiceProvider
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
            __DIR__ . '/../config/laravel-mail-integration.php' => config_path('laravel-mail-integration.php'),
        ]);

        if ($this->app->runningInConsole()) {
            // Export the migration
            if (!class_exists('CreateMailAccessTokensTable')) {
                $this->publishes([
                    __DIR__ . '/../database/migrations/create_mail_access_tokens_table.php.stub' => database_path('migrations' . date('Y_m_d_His', time()) . '_create_social_access_tokens.php'),
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
        $this->app->bind('laravel-mail', function ($app, $client) {
            return LaravelMailIntegration::service($client['client']);
        });
    }
}
