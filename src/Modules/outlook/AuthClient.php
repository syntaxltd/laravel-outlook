<?php

namespace Syntax\LaravelSocialIntegration\Modules\outlook;

use League\OAuth2\Client\Provider\GenericProvider;
use Syntax\LaravelSocialIntegration\Contracts\SocialClientAuth;

class AuthClient implements SocialClientAuth
{
    public function getAuthorizationUrl(): string
    {
        return $this->getOAuthClient()->getAuthorizationUrl();
    }

    public function getOAuthClient(): GenericProvider
    {
        $client = new GenericProvider([
            'clientId' => config('laravel-social-integration.services.outlook.appId'),
            'clientSecret' => config('laravel-social-integration.services.outlook.appSecret'),
            'redirectUri' => config('laravel-social-integration.services.outlook.redirectUri'),
            'urlAuthorize' => config('laravel-social-integration.services.outlook.authority') . config('laravel-social-integration.services.outlook.authorizeEndpoint'),
            'urlAccessToken' => config('laravel-social-integration.services.outlook.authority') . config('laravel-social-integration.services.outlook.tokenEndpoint'),
            'urlResourceOwnerDetails' => '',
            'scopes' => config('laravel-social-integration.services.outlook.scopes')
        ]);

        // Save client state so we can validate in callback
        session(['oauthState' => $client->getState()]);

        return $client;
    }
}