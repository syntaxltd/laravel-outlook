<?php


namespace Dytechltd\LaravelOutlook;

use Dytechltd\LaravelOutlook\Traits\Configurable;
use Google_Client;
use Google_Service_Gmail;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Exception;
use \Safe\file_get_contents;
use \Safe\json_decode;

class LaravelGmail extends Google_Client
{

    use Configurable {
        Configurable::__construct as configConstruct;
    }
    protected $emailAddress;
    protected $refreshToken;
    protected $app;
    protected $accessToken;
    protected $token;
    protected $userId;

    public function __construct(array $config = array(), $userId = null)
    {

        $this->userId = $userId;

        $this->configConstruct($config);

        parent::__construct($this->getConfigs());

        $this->configApi();

        if ($this->checkPreviouslyLoggedIn()) {
            $this->refreshTokenIfNeeded();
        }

    }

    /**
     * Gets the URL to authorize the user
     *
     * @return string
     */
    public function getOAuthClient(): string
    {
        return $this->createAuthUrl();
    }

    /**
     * Check and return true if the user has previously logged in without checking if the token needs to refresh
     *
     * @return bool
     */
    public function checkPreviouslyLoggedIn(): bool
    {

        return !$this->config();
    }

    /**
     * Refresh the auth token if needed
     *
     * @return mixed|null
     */
    private function refreshTokenIfNeeded(): mixed
    {
        if ($this->isAccessTokenExpired()) {
            $this->fetchAccessTokenWithRefreshToken($this->getRefreshToken());
            $token = $this->getAccessToken();
            parent::setAccessToken($token);
            $this->saveAccessToken($token);

            return $token;
        }

        return $this->token;
    }


    /**
     * Save the credentials in a file
     *
     * @param  array  $config
     */
    public function saveAccessToken(array $config)
    {
        $disk = Storage::disk('local');
        $fileName = config('credentials_file_name');
        $file = "gmail/tokens/$fileName.json";
        $allowJsonEncrypt = config('gmail.allow_json_encrypt');
        $config['email'] = $this->emailAddress;

        if ($disk->exists($file)) {

            if (empty($config['email'])) {
                if ($allowJsonEncrypt) {
                    $savedConfigToken = json_decode(decrypt($disk->get($file)), true);
                } else {
                    $savedConfigToken = json_decode($disk->get($file), true);
                }
                if(isset( $savedConfigToken['email'])) {
                    $config['email'] = $savedConfigToken['email'];
                }
            }

            $disk->delete($file);
        }

        if ($allowJsonEncrypt) {
            $disk->put($file, encrypt(json_encode($config)));
        } else {
            $disk->put($file, json_encode($config));
        }

    }
}