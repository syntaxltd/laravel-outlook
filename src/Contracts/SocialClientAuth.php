<?php

namespace Syntax\LaravelSocialIntegration\Contracts;

interface SocialClientAuth
{
    public function getOAuthClient(): mixed;

    public function getConfig(): array;
}
