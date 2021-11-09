<?php

namespace Syntax\LaravelSocialIntegration\Modules\outlook;

use Illuminate\Http\Request;
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
        $client = new GenericProvider($this->getConfigs());

        // Save client state so we can validate in callback
        session(['oauthState' => $client->getState()]);

        return $client;
    }

    /**
     * @return array
     */
    public function getConfigs(): array
    {
        return [
            'clientId' => config('laravel-social-integration.services.outlook.appId'),
            'clientSecret' => config('laravel-social-integration.services.outlook.appSecret'),
            'redirectUri' => config('laravel-social-integration.services.outlook.redirectUri'),
            'urlAuthorize' => config('laravel-social-integration.services.outlook.authority') . config('laravel-social-integration.services.outlook.authorizeEndpoint'),
            'urlAccessToken' => config('laravel-social-integration.services.outlook.authority') . config('laravel-social-integration.services.outlook.tokenEndpoint'),
            'urlResourceOwnerDetails' => '',
            'scopes' => config('laravel-social-integration.services.outlook.scopes')
        ];
    }

    public function storeToken(Request $request): void
    {
        // TODO: Implement storeToken() method.
    }
}
