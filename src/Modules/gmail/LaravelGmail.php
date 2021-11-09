<?php


namespace Syntax\LaravelSocialIntegration\Modules\gmail;

use Carbon\Carbon;
use Google_Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Syntax\LaravelSocialIntegration\Models\SocialAccessToken;

class LaravelGmail extends Google_Client
{
    public function auth(): AuthClient
    {
        return new AuthClient();
    }
    /**
     * @return array|string
     * @throws \Exception
     */
    public function makeToken()
    {
        if (parent::isAccessTokenExpired()) {
            $request = Request::capture();
            $code = (string) $request->input('code', null);
            if (!is_null($code) && !empty($code)) {
                $accessToken = $this->fetchAccessTokenWithAuthCode($code);
                parent::setAccessToken($accessToken);
                $this->storeTokens($accessToken);
                return $accessToken;
            } else {
                throw new \Exception('No access token');
            }
        } else {
            return $this->getAccessToken();
        }
    }

    /**
     * Save received access tokens from social account.
     *
     * @param array $accessToken
     *
     * @return void
     */
    public function storeTokens(array $accessToken): void
    {
        SocialAccessToken::updateOrCreate(
            ['partner_user_id' => Auth::id()],
            [
                'access_token' => $accessToken['access_token'],
                'refresh_token' => $accessToken['refresh_token'],
                'expires_at' => Carbon::parse(now())->addSeconds($accessToken['expires_in'])
            ]);
    }

}