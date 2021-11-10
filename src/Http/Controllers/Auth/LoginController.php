<?php

namespace Syntax\LaravelSocialIntegration\Http\Controllers\Auth;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Redirect;
use Syntax\LaravelSocialIntegration\Http\Controllers\Controller;
use Syntax\LaravelSocialIntegration\LaravelSocialIntegration;
use Throwable;

class LoginController extends Controller
{
    /**
     * @throws Throwable
     */
    public function logout(string $client): Redirector|Application|RedirectResponse
    {
        LaravelSocialIntegration::service($client)->auth()->clearTokens();

        return Redirect::to('https://' . tenant()->primary_domain . ':8081/');
    }
}
