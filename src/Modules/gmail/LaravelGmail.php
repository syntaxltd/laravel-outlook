<?php


namespace Syntax\LaravelSocialIntegration\Modules\gmail;

use Google_Client;

class LaravelGmail extends Google_Client
{
    public function auth(): AuthClient
    {
        return new AuthClient();
    }
}
