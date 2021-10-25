<?php

namespace Dytechltd\LaravelOutlook;

use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;
use Microsoft\Graph\Model\User;

class LaravelOutlook
{
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

    public function storeTokens(AccessToken $accessToken, User $user)
    {
        session([
            'accessToken' => $accessToken->getToken(),
            'refreshToken' => $accessToken->getRefreshToken(),
            'tokenExpires' => $accessToken->getExpires(),
            'userName' => $user->getDisplayName(),
            'userEmail' => null !== $user->getMail() ? $user->getMail() : $user->getUserPrincipalName(),
            'userTimeZone' => $user->getMailboxSettings()->getTimeZone()
        ]);
    }

    public function clearTokens()
    {
        session()->forget('accessToken');
        session()->forget('refreshToken');
        session()->forget('tokenExpires');
        session()->forget('userName');
        session()->forget('userEmail');
        session()->forget('userTimeZone');
    }

    public function getAccessToken()
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

    public function updateTokens(AccessToken $accessToken)
    {
        session([
            'accessToken' => $accessToken->getToken(),
            'refreshToken' => $accessToken->getRefreshToken(),
            'tokenExpires' => $accessToken->getExpires()
        ]);
    }
}
