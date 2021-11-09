<?php


namespace Syntax\LaravelSocialIntegration\Modules\gmail;

class LaravelGmail extends \Google_Client
{
    public function auth(): AuthClient
    {
        return new AuthClient();
    }

    public function messages(): mixed
    {
        return new MailClient();
    }
}
