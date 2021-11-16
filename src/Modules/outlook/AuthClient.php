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

        $url = $client->getAuthorizationUrl(['state' => tenant()->id]);

        // Save client state so we can validate in callback
        session(['oauthState' => $client->getState()]);

        info('', [session('oauthState')]);

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
            'clientId' => config('laravel-social-integration.messages.outlook.appId'),
            'clientSecret' => config('laravel-social-integration.messages.outlook.appSecret'),
            'redirectUri' => config('laravel-social-integration.messages.outlook.redirectUri'),
            'urlAuthorize' => config('laravel-social-integration.messages.outlook.authority') . config('laravel-social-integration.messages.outlook.authorizeEndpoint'),
            'urlAccessToken' => config('laravel-social-integration.messages.outlook.authority') . config('laravel-social-integration.messages.outlook.tokenEndpoint'),
            'urlResourceOwnerDetails' => '',
            'scopes' => config('laravel-social-integration.messages.outlook.scopes'),
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
        $providedState = $request->input('state');

        throw_if(!isset($expectedState) || !isset($providedState) || $expectedState != $providedState, new InvalidStateException);

        // Authorization code should be in the "code" query param
        $authCode = $request->input('code');
        if (isset($authCode)) {
            /**
             * @var AccessToken $accessToken
             */
            $accessToken = $this->getOAuthClient()->getAccessToken('authorization_code', [
                'code' => $authCode,
            ]);

            $this->saveToken($accessToken);
        }
    }

    public function clearTokens(): void
    {
        SocialAccessToken::query()->where('partner_user_id', auth('partneruser')->id())->delete();
    }

    /**
     * @throws Throwable
     */
    public function getToken(): string
    {
        /** @var SocialAccessToken|null $accessToken */
        $accessToken = SocialAccessToken::query()->where('partner_user_id', auth('partneruser')->id())->first();

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

    private function saveToken(AccessToken $accessToken): SocialAccessToken|Model
    {
        return SocialAccessToken::query()->updateOrCreate(['partner_user_id' => auth('partneruser')->id()], [
            'access_token' => $accessToken->getToken(),
            'refresh_token' => $accessToken->getRefreshToken(),
            'expires_at' => $accessToken->getExpires(),
            'type' => 'Bearer',
        ]);
    }
}
