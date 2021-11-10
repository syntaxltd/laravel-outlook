<?php


namespace Syntax\LaravelSocialIntegration\Modules\gmail;

use Google_Service_Gmail;
use Illuminate\Http\Request;
use Swift_Message;
use Syntax\LaravelSocialIntegration\Contracts\SocialClient;
use Syntax\LaravelSocialIntegration\Models\SocialAccessToken;
use Syntax\LaravelSocialIntegration\Modules\gmail\traits\Configurable;
use Syntax\LaravelSocialIntegration\Modules\gmail\traits\SendMail;

class MailClient extends \Google_Client implements SocialClient
{

    use Configurable;
    use SendMail;

    /**
     * @var
     */
    public $id;

    /**
     * @var
     */
    public $userId;

    /**
     * @var
     */
    public $internalDate;

    /**
     * @var
     */
    public $labels;

    /**
     * @var
     */
    public $size;

    /**
     * @var
     */
    public $threadId;

    /**
     * @var
     */
    public $historyId;

    /**
     * @var \Google_Service_Gmail_MessagePart
     */
    public $payload;

    protected string $emailAddress;
    public Google_Service_Gmail $service;

    public function __construct()
    {
        parent::__construct($this->getConfigs());

        $this->configApi();

        if ($this->isAccessTokenExpired()) {
            $this->refreshTokenIfNeeded();
        }
        $this->service = new Google_Service_Gmail($this);
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

    /**
     * @param int $id
     *
     * @return \Google_Service_Gmail_Message
     */
    public function get(int $id): \Google_Service_Gmail_Message
    {
        return $this->service->users_messages->get('me', $id);
    }
    /**
     * Sends a new email
     *
     * @return self
     */
    public function send(Request $request): static
    {
        $this->to($request->input('to'));
        $this->from($request->input('from'));
        $this->cc($request->input('cc'));
        $this->bcc($request->input('bcc'));
        $this->subject($request->input('subject'));
        $this->message($request->input('message'));
        $this->sendMail();

        return $this;
    }

    protected function setMessage(\Google_Service_Gmail_Message $message)
    {
        // TODO: Implement setMessage() method.
    }
}