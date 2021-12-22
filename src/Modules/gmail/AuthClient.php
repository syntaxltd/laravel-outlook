<?php


namespace Syntax\LaravelMailIntegration\Modules\gmail;

use Google\Service\Gmail\WatchResponse;
use Google_Service_Gmail;
use Google_Service_Gmail_Profile;
use Google_Service_Gmail_WatchRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Syntax\LaravelMailIntegration\Contracts\MailClientAuth;
use Syntax\LaravelMailIntegration\Exceptions\InvalidStateException;
use Syntax\LaravelMailIntegration\Models\MailAccessToken;
use Syntax\LaravelMailIntegration\Modules\gmail\traits\Configurable;
use Throwable;

class AuthClient extends \Google_Client implements MailClientAuth
{
    use Configurable;

    public Google_Service_Gmail $service;

    public function __construct()
    {
        parent::__construct($this->getConfigs());

        $this->configApi();

        $this->service = new Google_Service_Gmail($this);
    }

    public function getAuthorizationUrl(): string
    {
        return $this->getOAuthClient();
    }

    /**
     * Gets the URL to authorize the user
     *
     * @return string
     */
    public function getOAuthClient(): string
    {
        $this->setState(tenant('id'));

        return $this->createAuthUrl();
    }

    /**
     * @throws Throwable
     */
    public function storeToken(Request $request): void
    {
        /** @var string|null $code */
        $code = $request->input('code');

        throw_if(is_null($code), new InvalidStateException('No access token.'));

        $accessToken = $this->fetchAccessTokenWithAuthCode($code);

        parent::setAccessToken($accessToken);
        $me = $this->getProfile();
        if (property_exists($me, 'emailAddress')) {
            $accessToken['email'] = $me->emailAddress;
            $this->subscriptions();
        }

        MailAccessToken::query()->updateOrCreate([
            'partner_user_id' => auth('partneruser')->id(),
            'type' => 'gmail',
        ], [
            'access_token' => $accessToken['access_token'],
            'refresh_token' => $accessToken['refresh_token'],
            'expires_in' => $accessToken['expires_in'],
            'email' => $accessToken['email'],
            'user_mail_id' => $accessToken['email'],
        ]);
    }

    /**
     * Gets user profile from Gmail
     *
     * @return Google_Service_Gmail_Profile
     */
    public function getProfile(): Google_Service_Gmail_Profile
    {
        return $this->service->users->getProfile('me');
    }

    public function clearTokens(): void
    {
        //$this->stopWatch();
        $this->revokeToken();

        // Change to get Social Access Token for authenticated users
        MailAccessToken::Where('partner_user_id', Auth::id())->where('type', 'gmail')->delete();
    }

    /**
     * users.stop receiving push notifications for the given user mailbox.
     *
     * @return Google_Service_Gmail
     */
    public function stopWatch(): Google_Service_Gmail
    {
        return $this->service->users->stop('me');
    }

    /**
     * Set up or update a push notification watch on the given user mailbox.
     *
     * @return WatchResponse
     */
    public function subscriptions(): WatchResponse
    {
        $projectId = config('laravel-mail-integration.services.gmail.project_id');
        $rq = new Google_Service_Gmail_WatchRequest();
        $rq->setTopicName('projects/'.$projectId.'/topics/mykii');
        return $this->service->users->watch('me', $rq);
    }

}
