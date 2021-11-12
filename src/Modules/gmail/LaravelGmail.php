<?php


namespace Syntax\LaravelSocialIntegration\Modules\gmail;

use Illuminate\Support\Facades\Log;

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
