<?php


namespace Syntax\LaravelSocialIntegration\Modules\gmail;

use Syntax\LaravelSocialIntegration\Contracts\SocialClientAuth;
use Syntax\LaravelSocialIntegration\Modules\gmail\traits\Configurable;

class AuthClient extends \Google_Client implements SocialClientAuth
{
    use Configurable {
        Configurable::__construct as configConstruct;
    }

    public function __construct()
    {
        parent::__construct($this->getConfigs());

        $this->configApi();
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
        return $this->createAuthUrl();
    }
}