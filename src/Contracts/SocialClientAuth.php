<?php

namespace Syntax\LaravelSocialIntegration\Contracts;

use Illuminate\Http\Request;

interface SocialClientAuth
{
    public function getOAuthClient(): mixed;

    public function storeToken(Request $request): void;
}
