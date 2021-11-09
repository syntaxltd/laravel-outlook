<?php

namespace Syntax\LaravelSocialIntegration;

use Microsoft\Graph\Graph;
use Syntax\LaravelSocialIntegration\Exceptions\InvalidClientException;
use Syntax\LaravelSocialIntegration\Modules\gmail\LaravelGmail;
use Syntax\LaravelSocialIntegration\Modules\outlook\LaravelOutlook;
use Throwable;

class LaravelSocialIntegration
{
    public function getGraphClient(): Graph
    {
        return (new Graph)->setAccessToken(self::service('outlook')->getAccessToken());
    }

    /**
     * @param string $client
     * @return mixed
     * @throws Throwable
     */
    public static function service(string $client): mixed
    {
        throw_if(!in_array($client, config('laravel-social-integration.default')), new InvalidClientException);

        $services = [
            'gmail' => LaravelGmail::class,
            'outlook' => LaravelOutlook::class,
        ];

        return new $services[$client];
    }
}
