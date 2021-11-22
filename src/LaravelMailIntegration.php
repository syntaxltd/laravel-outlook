<?php

namespace Syntax\LaravelMailIntegration;

use Illuminate\Support\Facades\Auth;
use Syntax\LaravelMailIntegration\Exceptions\InvalidClientException;
use Syntax\LaravelMailIntegration\Models\MailAccessToken;
use Syntax\LaravelMailIntegration\Modules\gmail\LaravelGmail;
use Syntax\LaravelMailIntegration\Modules\outlook\LaravelOutlook;
use Throwable;

class LaravelMailIntegration
{
    /**
     * @param string $client
     * @return mixed
     * @throws Throwable
     */
    public static function service(string $client): mixed
    {
        throw_if(!in_array($client, config('laravel-mail-integration.default')), new InvalidClientException);

        $services = [
            'gmail' => LaravelGmail::class,
            'outlook' => LaravelOutlook::class,
        ];

        return new $services[$client];
    }

    public function getProviders(): mixed
    {
        return MailAccessToken::Where('partner_user_id', Auth::id())->get();
    }

}
