<?php


namespace Dytechltd\LaravelOutlook;

use Dytechltd\LaravelOutlook\Exceptions\InvalidStateException;
use Dytechltd\LaravelOutlook\Services\Message;
use Dytechltd\LaravelOutlook\Traits\Configurable;
use Google_Client;
use Google_Service_Gmail;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
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
    public $userId;

    public function __construct($config = array(), $userId = null)
    {
        $this->userId = $userId;

        $this->configConstruct($config);

        parent::__construct($this->getConfigs());

        $this->configApi();
    }

    /**
     * Gets the URL to authorize the user
     *
     * @return string
     */
    public function getOAuthClient(): string
    {
        $this->setState(base64_encode(tenant('id')));
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
     * Save the credentials in a file
     *
     * @param array $config
     * @throws FileNotFoundException
     */
    public function storeTokens(array $config): void
    {
        $disk = Storage::disk('local');
        $fileName = config('gmail.credentials_file_name');
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

    /**
     * @return array|string|null
     */
    public function getAccessToken(): array|string|null
    {
        return parent::getAccessToken() ?: $this->config();
    }

    /**
     * @return Message
     * @throws InvalidStateException
     */
    public function getMessages(): Message
    {
        if ($this->getAccessToken()) {
            return new Message($this);
        }else{
            throw new InvalidStateException('No credentials found.');
        }

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

}