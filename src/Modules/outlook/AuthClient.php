<?php

namespace Syntax\LaravelMailIntegration\Modules\outlook;

use App\Models\CentralMail;
use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;
use Microsoft\Graph\Exception\GraphException;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model\Subscription;
use Microsoft\Graph\Model\User;
use Syntax\LaravelMailIntegration\Contracts\MailClientAuth;
use Syntax\LaravelMailIntegration\Exceptions\InvalidStateException;
use Syntax\LaravelMailIntegration\Models\MailAccessToken;
use Throwable;

class AuthClient implements MailClientAuth
{
    private string $userId;

    public function __construct(string $userId)
    {
        $this->userId = $userId;
    }

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

            // Get graph User
            $user = $this->user($accessToken->getToken());

            // Save the token
            $this->saveToken($accessToken, $user);

            $this->subscribe();
        }
    }

    /**
     * @param string $token
     * @return User
     * @throws GraphException
     * @throws GuzzleException
     */
    public function user(string $token): User
    {
        $graph = new Graph();
        return $graph->setAccessToken($token)
            ->createRequest('GET', '/me?$select=displayName,mail,mailboxSettings,userPrincipalName')
            ->setReturnType(User::class)
            ->execute();
    }

    private function saveToken(AccessToken $accessToken, User $user): MailAccessToken
    {
        /**
         * @var MailAccessToken $token
         */
        $token = MailAccessToken::query()->updateOrCreate([
            'type' => 'outlook',
            'partner_user_id' => $this->userId,
        ], [
            'access_token' => $accessToken->getToken(),
            'refresh_token' => $accessToken->getRefreshToken(),
            'expires_at' => $accessToken->getExpires(),
            'email' => $user->getMail() ?? $user->getUserPrincipalName(),
        ]);

        // Save central mail user and subscribe to notifications.
        CentralMail::query()->updateOrCreate(['tenant_id' => tenant()->id, 'email' => $user->getId()]);

        return $token;
    }

    /**
     * @return Subscription
     * @throws GraphException
     * @throws GuzzleException
     * @throws Throwable
     */
    public function subscribe(): Subscription
    {
        return $this->getGraphClient()->createRequest('POST', '/subscriptions')->attachBody([
            'changeType' => 'created',
            'notificationUrl' => config('app.url') . '/partner/oauth/notifications/outlook',
            'resource' => "/me/messages",
            'expirationDateTime' => Carbon::now()->addDays(2),
            'clientState' => 'SecretClientState',
        ])->setReturnType(Subscription::class)->execute();
    }

    /**
     * @throws Throwable
     */
    public function getGraphClient(): Graph
    {
        return (new Graph)->setAccessToken($this->getToken());
    }

    /**
     * @throws Throwable
     */
    public function getToken(): ?string
    {
        /**
         * @var MailAccessToken|null $accessToken
         */
        $accessToken = MailAccessToken::query()->where('partner_user_id', $this->userId)->first();

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
                /**
                 * @var AccessToken $newToken
                 */
                $newToken = $this->getOAuthClient()->getAccessToken('refresh_token', [
                    'refresh_token' => $accessToken->refresh_token,
                ]);

                // Store the new values
                return $this->saveToken($newToken, $this->user($newToken))->access_token;
            } catch (IdentityProviderException $e) {
                return '';
            }
        }

        // Token is still valid, just return it
        return $accessToken->access_token;
    }

    public function clearTokens(): void
    {
        MailAccessToken::query()->where('partner_user_id', auth('partneruser')->id())->delete();
    }

    /**
     * @throws GraphException
     * @throws GuzzleException
     * @throws Throwable
     */
    public function updateSubscription(?string $id): ?Subscription
    {
        $this->getGraphClient()
            ->createRequest('PATCH', "/subscriptions/$id")
            ->attachBody(['expirationDateTime' => Carbon::now()->addDays(2)])
            ->setReturnType(Subscription::class)
            ->execute();

        return $this->subscriptions();
    }

    /**
     * @throws GraphException
     * @throws GuzzleException
     * @throws Throwable
     */
    public function subscriptions(): ?Subscription
    {
        $subscription = $this->getGraphClient()
            ->createRequest('GET', "/subscriptions")
            ->setReturnType(Subscription::class)
            ->execute();

        return count($subscription) > 0 ? $subscription[0] : null;
    }

    /**
     * @param string|null $id
     * @throws GraphException
     * @throws GuzzleException
     * @throws Throwable
     */
    public function unsubscribe(?string $id): void
    {
        if (!is_null($id)) {
            $this->getGraphClient()->createRequest('DELETE', "/subscriptions/$id")
                ->execute();
        }
    }
}
