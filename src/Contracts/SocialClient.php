<?php

namespace Syntax\LaravelSocialIntegration\Contracts;

use Illuminate\Http\Request;

interface SocialClient
{
    public function send(Request $request);
}
