<?php

namespace Syntax\LaravelSocialIntegration\Modules\outlook;

class LaravelOutlook
{
    public function auth(): AuthClient
    {
        return new AuthClient;
    }
}
