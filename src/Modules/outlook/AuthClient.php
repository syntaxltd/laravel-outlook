<?php

namespace Syntax\LaravelSocialIntegration\Modules\outlook;

use Illuminate\Database\Eloquent\Model;
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

                $this->saveToken($accessToken);
            } catch (IdentityProviderException $exception) {
                throw $exception;
            }
        }
    }

    private function saveToken(AccessToken $accessToken): SocialAccessToken|Model
    {
        return SocialAccessToken::query()->updateOrCreate(['partner_user_id' => '8da62473-1897-429a-9b76-1285c34fe4c7'], [
            'access_token' => $accessToken->getToken(),
            'refresh_token' => $accessToken->getRefreshToken(),
            'expires_at' => $accessToken->getExpires(),
            'type' => 'Bearer',
        ]);
    }

    public function clearTokens(): void
    {
        SocialAccessToken::query()->where('partner_user_id', '8da62473-1897-429a-9b76-1285c34fe4c7')->delete();
    }

    /**
     * @throws Throwable
     */
    public function getToken(): string
    {
        /** @var SocialAccessToken|null $accessToken */
        $accessToken = SocialAccessToken::query()->where('partner_user_id', '8da62473-1897-429a-9b76-1285c34fe4c7')->first();

        // Check if tokens exist
        if (is_null($accessToken)) {
            return '';
        }

        // Check if token is expired
        //Get current time + 5 minutes (to allow for time differences)
        $now = time() + 300;
        if ($accessToken->expires_at <= $now) {
            // Token is expired (or very close to it)
            // so let's refresh
            try {
                $newToken = $this->getOAuthClient()->getAccessToken('refresh_token', [
                    'refresh_token' => $accessToken->refresh_token,
                ]);

                // Store the new values
                return $this->saveToken($newToken)->access_token;
            } catch (IdentityProviderException $e) {
                return '';
            }
        }

        // Token is still valid, just return it
        return $accessToken->access_token;
    }
}
