<?php

namespace Syntax\LaravelMailIntegration\Contracts;

use Illuminate\Http\Request;

interface MailClientAuth
{
    public function getOAuthClient(): mixed;

    public function storeToken(Request $request): void;

    public function clearTokens(): void;
//
//    public function getToken(): string|null;
}
