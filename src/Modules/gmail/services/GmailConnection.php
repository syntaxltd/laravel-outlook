<?php


namespace Syntax\LaravelMailIntegration\Modules\gmail\services;

use Google_Client;
use Google_Service_Gmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Syntax\LaravelMailIntegration\Models\MailAccessToken;
use Syntax\LaravelMailIntegration\Modules\gmail\traits\Configurable;

class GmailConnection extends Google_Client
{

    use Configurable;

    protected string $emailAddress;

    public Google_Service_Gmail $service;

    public string|null $userId;

    protected $token;

    public function __construct(string $userId = null)
    {
        $this->userId = $userId;

        parent::__construct($this->getConfigs());

        $this->configApi();

        $this->service = new Google_Service_Gmail($this);

        if ($this->checkPreviouslyLoggedIn()) {
           $this->refreshTokenIfNeeded();
        }

    }
    /**
     * Check and return true if the user has previously logged in without checking if the token needs to refresh
     *
     * @return bool
     */
    public function checkPreviouslyLoggedIn(): bool
    {
        if (property_exists(get_class($this), 'userId') && $this->userId) {
            $savedConfigToken = MailAccessToken::Where('partner_user_id', $this->userId)->where('type', 'gmail')->first();
            return !empty($savedConfigToken->access_token);
        } elseif (auth()->user()) {
            $this->userId = Auth::id();
            $savedConfigToken = MailAccessToken::Where('partner_user_id', $this->userId)->where('type', 'gmail')->first();
            return !empty($savedConfigToken->access_token);
        }else{
            return false;
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
        $token = parent::getAccessToken() ? [parent::getAccessToken()] : MailAccessToken::Where('partner_user_id', $this->userId)->where('type', 'gmail')->get()->toArray();
        if (!empty($token)) {
            $this->setAccessToken($token[0]);
            return true;
        }else {
            return false;
        }
    }
    /**
     * Refresh the auth token if needed
     *
     * @return array
     */
    private function refreshTokenIfNeeded(): array
    {
        if ($this->isAccessTokenExpired()) {
            $this->fetchAccessTokenWithRefreshToken($this->getRefreshToken());
            $token = $this->getAccessToken();
            parent::setAccessToken($token);
            return $token;
        }

        return $this->token;
    }
}