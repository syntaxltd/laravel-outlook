<?php


namespace Syntax\LaravelSocialIntegration\Modules\gmail;

use Carbon\Carbon;
use Google_Client;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\Request;
use Safe\file_get_contents;
use Safe\json_decode;
use Syntax\LaravelSocialIntegration\Exceptions\InvalidStateException;
use Syntax\LaravelSocialIntegration\Models\SocialAccessToken;
use Syntax\LaravelSocialIntegration\Services\Message;
use Syntax\LaravelSocialIntegration\Traits\Configurable;

class LaravelGmail extends Google_Client
{

    use Configurable {
        Configurable::__construct as configConstruct;
    }

    public $userId;
    protected $emailAddress;
    protected $refreshToken;
    protected $app;
    protected $accessToken;
    protected $token;
    private $configuration;

    public function __construct($config = array(), $userId = null)
    {
        $this->userId = $userId;

        $this->configConstruct($config);

        $this->configuration = $config;

        parent::__construct($this->getConfigs());

        $this->configApi();

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
        $token = SocialAccessToken::where('partner_user_id', $this->userId)->get();
        if (!empty($token)) {
            return !empty($token->access_token);
        }

        return false;
    }

    /**
     * Refresh the auth token if needed
     *
     * @return mixed
     * @throws FileNotFoundException
     */
    private function refreshTokenIfNeeded(): mixed
    {
        if ($this->isAccessTokenExpired()) {
            $this->fetchAccessTokenWithRefreshToken($this->getRefreshToken());
            $token = $this->getAccessToken();
            $this->setAccessToken($token);
            $this->storeTokens($token);

            return $token;
        }

        return $this->token;
    }

    /**
     * @return array|string|null
     */
    public function getAccessToken(): array|string|null
    {
        $token = parent::getAccessToken() ?: $this->config();

        return $token;
    }

    /**
     * Save the credentials in a file
     *
     * @param array $config
     * @throws FileNotFoundException
     */
    public function storeTokens(array $config): void
    {
        SocialAccessToken::updateOrCreate(
            ['partner_user_id' => $this->userId],
            [
                'access_token' => $config['access_token'],
                'refresh_token' => $config['refresh_token'],
                'expires_at' => Carbon::parse(now())->addSeconds($config['expires_in'])
            ]);
    }

    /**
     * Gets the URL to authorize the user
     *
     * @return string
     */
    public function getOAuthClient(): string
    {
        $this->setState(base64_encode($this->userId));

        return $this->createAuthUrl();
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
     * @return Message
     * @throws InvalidStateException
     */
    public function getMessages(): Message
    {

        if (!$this->getToken()) {
            throw new InvalidStateException('No credentials found.');
        }

        return new Message($this);

    }

    public function getToken()
    {
        return parent::getAccessToken() ?: $this->config();
    }

    /**
     * Updates / sets the current userId for the service
     *
     * @return LaravelGmail
     */
    public function setUserId($userId): static
    {
        $this->userId = $userId;

        return $this;
    }

    /**
     * Returns the Gmail user email
     *
     * @return \Google_Service_Gmail_Profile
     */
    public function user()
    {
        return $this->config('email');
    }

    public function logout(): void
    {
        $this->revokeToken();
        $this->deleteAccessToken();
    }

    /**
     * Delete the credentials in a file
     */
    public function deleteAccessToken(): void
    {
    }
}