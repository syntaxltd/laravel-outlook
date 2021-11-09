<?php

namespace Syntax\LaravelSocialIntegration\Contracts;

interface SocialClientAuth
{
    public function getOAuthClient();

    public function getAuthorizationUrl();
}
