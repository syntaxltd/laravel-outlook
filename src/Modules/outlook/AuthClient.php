<?php

namespace Syntax\LaravelSocialIntegration\Modules\outlook;

use Illuminate\Http\Request;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;
use Syntax\LaravelSocialIntegration\Contracts\SocialClientAuth;
use Syntax\LaravelSocialIntegration\Exceptions\InvalidStateException;
use Syntax\LaravelSocialIntegration\Models\SocialAccessToken;
use Throwable;

class AuthClient implements SocialClientAuth
{
    public function getAuthorizationUrl(): string
    {
        $client = $this->getOAuthClient();

        $url = $client->getAuthorizationUrl();

        // Save client state so we can validate in callback
        session(['oauthState' => $client->getState()]);

        return $url;
    }

    public function getOAuthClient(): GenericProvider
    {
        return new GenericProvider($this->getConfigs());
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

    /**
     * @throws Throwable
     */
    public function storeToken(Request $request): void
    {
        // Validate the state
        $expectedState = session('oauthState');
        $request->session()->forget('oauthState');
        $providedState = $request->query('state');

        throw_if(!isset($expectedState) || !isset($providedState) || $expectedState != $providedState, new InvalidStateException);

        // Authorization code should be in the "code" query param
        $authCode = $request->query('code');
        if (isset($authCode)) {
            try {
                /**
                 * @var AccessToken $accessToken
                 */
                $accessToken = $this->getOAuthClient()->getAccessToken('authorization_code', [
                    'code' => $authCode,
                ]);

                SocialAccessToken::query()->updateOrCreate(['partner_user_id' => '0a7b9e4a-3c1a-4777-a370-fd447f77002b'], [
                    'access_token' => $accessToken->getToken(),
                    'refresh_token' => $accessToken->getRefreshToken(),
                    'expires_at' => $accessToken->getExpires(),
                    'type' => 'Bearer',
                ]);

            } catch (IdentityProviderException $exception) {
                throw $exception;
            }
        }
    }

    public function clearTokens(): void
    {
        SocialAccessToken::query()->where('partner_user_id', '0a7b9e4a-3c1a-4777-a370-fd447f77002b')->delete();
    }

    public function getToken(): string|null
    {
        /** @var SocialAccessToken|null $accessToken */
        $accessToken = SocialAccessToken::query()->where('partner_user_id', '0a7b9e4a-3c1a-4777-a370-fd447f77002b')->first();

        return $accessToken?->access_token;
    }
}
