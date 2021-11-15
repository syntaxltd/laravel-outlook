<?php


namespace Syntax\LaravelSocialIntegration\Modules\gmail\services;

use Google_Client;
use Google_Service_Gmail;
use Illuminate\Support\Facades\Auth;
use Syntax\LaravelSocialIntegration\Models\SocialAccessToken;
use Syntax\LaravelSocialIntegration\Modules\gmail\traits\Configurable;

class GmailConnection extends Google_Client
{

    use Configurable;

    protected string $emailAddress;

    public Google_Service_Gmail $service;

    public function __construct()
    {
        parent::__construct($this->getConfigs());

        $this->configApi();

        $this->service = new Google_Service_Gmail($this);

        if ($this->isAccessTokenExpired()) {
            $this->refreshTokenIfNeeded();
        }

    }

    /**
     * Check if token exists and is expired
     * Throws an AuthException when the auth file its empty or with the wrong token
     *
     *
     * @return bool Returns True if the access_token is expired.
     */
    public function isAccessTokenExpired(): bool
    {
        // Change to get Social Access Token for authenticated users
        $token = parent::getAccessToken() ? [parent::getAccessToken()] : SocialAccessToken::Where('partner_user_id', Auth::id())->where('type', 'gmail')->get()->toArray();
        if ($token) {
            $this->setAccessToken($token[0]);
        }
        return parent::isAccessTokenExpired();
    }

    /**
     * Refresh the auth token if needed
     *
     * @return array
     */
    private function refreshTokenIfNeeded(): array
    {
        $this->fetchAccessTokenWithRefreshToken($this->getRefreshToken());
        $token = $this->getAccessToken();
        parent::setAccessToken($token);
        return $token;
    }
}