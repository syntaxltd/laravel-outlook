<?php

namespace Syntax\LaravelSocialIntegration;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Syntax\LaravelSocialIntegration\Exceptions\InvalidClientException;
use Syntax\LaravelSocialIntegration\Models\SocialAccessEmail;
use Syntax\LaravelSocialIntegration\Models\SocialAccessToken;
use Syntax\LaravelSocialIntegration\Modules\gmail\LaravelGmail;
use Syntax\LaravelSocialIntegration\Modules\outlook\LaravelOutlook;
use Throwable;

class LaravelSocialIntegration
{
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

    public function getProviders(): mixed
    {
        return SocialAccessToken::Where('partner_user_id', Auth::id())->get();
    }

    public function getMessages(): mixed
    {
        $clients = SocialAccessToken::Where('partner_user_id', Auth::id())->pluck('id');
        Log::info('clients', [$clients]);
        return SocialAccessEmail::whereIn('social_access_token_id', $clients)->get();
    }
}
