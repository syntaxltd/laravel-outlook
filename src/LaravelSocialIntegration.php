<?php

namespace Syntax\LaravelSocialIntegration;

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model\User;

class LaravelSocialIntegration
{
    /**
     * Get microsoft generic provider.
     *
     * @return GenericProvider
     */
    public function getOAuthClient(): GenericProvider
    {
        return new GenericProvider([
            'clientId' => config('azure.appId'),
            'clientSecret' => config('azure.appSecret'),
            'redirectUri' => config('azure.redirectUri'),
            'urlAuthorize' => config('azure.authority') . config('azure.authorizeEndpoint'),
            'urlAccessToken' => config('azure.authority') . config('azure.tokenEndpoint'),
            'urlResourceOwnerDetails' => '',
            'scopes' => config('azure.scopes')
        ]);
    }

    /**
     * Save received access tokens from social account.
     *
     * @param AccessToken $accessToken
     * @param User $user
     *
     * @return void
     */
    public function storeTokens(AccessToken $accessToken, User $user): void
    {
        session([
            'accessToken' => $accessToken->getToken(),
            'refreshToken' => $accessToken->getRefreshToken(),
            'tokenExpires' => $accessToken->getExpires(),
            'userName' => $user->getDisplayName(),
            'userEmail' => null !== $user->getMail() ? $user->getMail() : $user->getUserPrincipalName(),
            'userTimeZone' => $user->getMailboxSettings()->getTimeZone(),
        ]);
    }

    /**
     * Delete saved access tokens.
     *
     * @return void
     */
    public function clearTokens(): void
    {
        session()->forget('accessToken');
        session()->forget('refreshToken');
        session()->forget('tokenExpires');
        session()->forget('userName');
        session()->forget('userEmail');
        session()->forget('userTimeZone');
    }

    public function getGraphClient(): Graph
    {
        return (new Graph)->setAccessToken($this->getAccessToken());
    }

    /**
     * Get saved access token.
     *
     * @return string|null
     */
    public function getAccessToken(): string|null
    {
        // Check if tokens exist
        if (empty(session('accessToken')) ||
            empty(session('refreshToken')) ||
            empty(session('tokenExpires'))) {
            return '';
        }

        // Check if token is expired
        //Get current time + 5 minutes (to allow for time differences)
        $now = time() + 300;
        if (session('tokenExpires') <= $now) {
            // Token is expired (or very close to it)
            // so let's refresh

            // Initialize the OAuth client
            $oauthClient = new GenericProvider([
                'clientId' => config('azure.appId'),
                'clientSecret' => config('azure.appSecret'),
                'redirectUri' => config('azure.redirectUri'),
                'urlAuthorize' => config('azure.authority') . config('azure.authorizeEndpoint'),
                'urlAccessToken' => config('azure.authority') . config('azure.tokenEndpoint'),
                'urlResourceOwnerDetails' => '',
                'scopes' => config('azure.scopes')
            ]);

            try {
                /** @var AccessToken $newToken */
                $newToken = $oauthClient->getAccessToken('refresh_token', [
                    'refresh_token' => session('refreshToken')
                ]);

                // Store the new values
                $this->updateTokens($newToken);

                return $newToken->getToken();
            } catch (IdentityProviderException $e) {
                return null;
            }
        }

        // Token is still valid, just return it
        return session('accessToken');
    }

    /**
     * Update saved access tokens.
     *
     * @param AccessToken $accessToken
     *
     * @return void
     */
    public function updateTokens(AccessToken $accessToken): void
    {
        session([
            'accessToken' => $accessToken->getToken(),
            'refreshToken' => $accessToken->getRefreshToken(),
            'tokenExpires' => $accessToken->getExpires()
        ]);
    }
}
