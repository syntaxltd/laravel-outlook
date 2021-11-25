<?php

namespace Syntax\LaravelMailIntegration\Modules\outlook;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model\User;
use Syntax\LaravelMailIntegration\Contracts\MailClientAuth;
use Syntax\LaravelMailIntegration\Exceptions\InvalidStateException;
use Syntax\LaravelMailIntegration\Models\MailAccessToken;
use Throwable;

class AuthClient implements MailClientAuth
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
            'clientId' => config('laravel-mail-integration.services.outlook.appId'),
            'clientSecret' => config('laravel-mail-integration.services.outlook.appSecret'),
            'redirectUri' => config('laravel-mail-integration.services.outlook.redirectUri'),
            'urlAuthorize' => config('laravel-mail-integration.services.outlook.authority') . config('laravel-mail-integration.services.outlook.authorizeEndpoint'),
            'urlAccessToken' => config('laravel-mail-integration.services.outlook.authority') . config('laravel-mail-integration.services.outlook.tokenEndpoint'),
            'urlResourceOwnerDetails' => '',
            'scopes' => config('laravel-mail-integration.services.outlook.scopes'),
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

            $graph = new Graph();
            $graph->setAccessToken($accessToken->getToken());
            $user = $graph->createRequest('GET', '/me?$select=userPrincipalName')
                ->setReturnType(User::class)
                ->execute();

            $this->saveToken($accessToken, $user->getMail() ?? $user->getUserPrincipalName());
        }
    }

    private function saveToken(AccessToken $accessToken, string $email): MailAccessToken|Model
    {
        return MailAccessToken::query()->updateOrCreate([
            'type' => 'gmail',
            'partner_user_id' => auth('partneruser')->id(),
        ], [
            'access_token' => $accessToken->getToken(),
            'refresh_token' => $accessToken->getRefreshToken(),
            'expires_at' => $accessToken->getExpires(),
            'type' => 'outlook',
            'email' => $email,
        ]);
    }

    public function clearTokens(): void
    {
        MailAccessToken::query()->where('partner_user_id', auth('partneruser')->id())->delete();
    }

    /**
     * @throws Throwable
     */
    public function getToken(): string
    {
        /** @var MailAccessToken|null $accessToken */
        $accessToken = MailAccessToken::query()->where('partner_user_id', auth('partneruser')->id())->first();

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
                return $this->saveToken($newToken, $accessToken->email)->access_token;
            } catch (IdentityProviderException $e) {
                return '';
            }
        }

        // Token is still valid, just return it
        return $accessToken->access_token;
    }
}
