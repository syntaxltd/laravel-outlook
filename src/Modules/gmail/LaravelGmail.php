<?php


namespace Syntax\LaravelSocialIntegration\Modules\gmail;

use Carbon\Carbon;
use Google_Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Syntax\LaravelSocialIntegration\Models\SocialAccessToken;

class LaravelGmail extends Google_Client
{
    public function auth(): AuthClient
    {
        return new AuthClient();
    }

}