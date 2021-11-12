<?php


namespace Syntax\LaravelSocialIntegration\Modules\gmail;

use Exception;
use Google_Client;
use Google_Service_Gmail;
use Google_Service_Gmail_Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Syntax\LaravelSocialIntegration\Contracts\SocialClient;
use Syntax\LaravelSocialIntegration\Models\SocialAccessEmail;
use Syntax\LaravelSocialIntegration\Models\SocialAccessToken;
use Syntax\LaravelSocialIntegration\Modules\gmail\traits\Configurable;
use Syntax\LaravelSocialIntegration\Modules\gmail\traits\SendMail;

class MailClient extends Google_Client implements SocialClient
{

    use Configurable;
    use SendMail;

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

    /**
     * @param int $id
     *
     * @return Google_Service_Gmail_Message
     */
    public function get(int $id): Google_Service_Gmail_Message
    {
        return $this->service->users_messages->get('me', $id);
    }

    /**
     * Sends a new email
     *
     * @param Request $request
     * @return SocialAccessEmail
     * @throws Exception
     */
    public function send(Request $request): SocialAccessEmail
    {
        $this->to('evamwng@gmail.com');
        $this->from(Auth::user()->email, Auth::user()->name);
        $this->cc($request->input('cc'));
        $this->bcc($request->input('bcc'));
        $this->subject($request->input('subject'));
        $this->message($request->input('message'));
        $this->sendMail();

        return $this->storeMessage();
    }

    private function getContacts(Request $request): array
    {
        return collect($request->input('contact'))->filter()->map(function ($item) {
            return $item['email'];
        })->toArray();
    }

    private function storeMessage(): SocialAccessEmail
    {
        return SocialAccessEmail::updateOrCreate(
            [
                'email_id' => $this->id
            ],[
            'social_access_token_id' => SocialAccessToken::Where('partner_user_id', Auth::id())->where('type', 'gmail')->first()->id,
            'thread_id' => $this->threadId,
            'to' => $this->to,
            'from' => $this->from,
            'cc' =>$this->cc,
            'bcc' => $this->bcc,
            'subject' => $this->subject,
            'message' => $this->message
        ]);
    }
}