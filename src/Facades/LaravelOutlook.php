<?php

namespace Syntax\LaravelMailIntegration\Facades;

use Illuminate\Support\Facades\Facade;
use RuntimeException;

class LaravelOutlook extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     *
     * @throws RuntimeException
     */
    protected static function getFacadeAccessor(): string
    {
        return 'laravel-outlook';
    }
}
