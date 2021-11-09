<?php


namespace Syntax\LaravelSocialIntegration\Modules\gmail;

use Google_Service_Gmail;
use Illuminate\Http\Request;
use Swift_Message;
use Syntax\LaravelSocialIntegration\Contracts\SocialClient;
use Syntax\LaravelSocialIntegration\Models\SocialAccessToken;
use Syntax\LaravelSocialIntegration\Modules\gmail\traits\Configurable;

class MailClient extends \Google_Client implements SocialClient
{

    use Configurable;

    protected string $emailAddress;
    public Google_Service_Gmail $service;
    private Swift_Message $swiftMessage;

    public function __construct()
    {
        parent::__construct($this->getConfigs());

        $this->configApi();

        if ($this->isAccessTokenExpired()) {
            $this->refreshTokenIfNeeded();
        }
        $this->service = new Google_Service_Gmail($this);

        $this->swiftMessage = new Swift_Message();
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
        $token = parent::getAccessToken() ?: SocialAccessToken::take(1)->first()->toArray();

        if ($token) {
            $this->setAccessToken($token);
        }

        return parent::isAccessTokenExpired();
    }

    /**
     * Refresh the auth token if needed
     *
     * @return mixed
     */
    private function refreshTokenIfNeeded(): mixed
    {
        $this->fetchAccessTokenWithRefreshToken($this->getRefreshToken());
        $token = $this->getAccessToken();
        parent::setAccessToken($token);
        return $token;
    }

    public function send(Request $request)
    {
        $this->service->users_messages->send('me', $request->all(), []);
    }

    /**
     * @param int $id
     *
     * @return \Google_Service_Gmail_Message
     */
    public function get(int $id): \Google_Service_Gmail_Message
    {
        return $this->service->users_messages->get('me', $id);
    }

}