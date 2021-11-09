<?php

namespace Syntax\LaravelSocialIntegration\Http\Controllers\Auth;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model\User;
use Syntax\LaravelSocialIntegration\Exceptions\InvalidStateException;
use Syntax\LaravelSocialIntegration\Http\Controllers\Controller;
use Syntax\LaravelSocialIntegration\LaravelSocialIntegration;
use Throwable;

class LoginController extends Controller
{
    /**
     * @throws Throwable
     */
    public function login(string $client): RedirectResponse
    {
        $authUrl = LaravelSocialIntegration::service($client)->auth()->getAuthorizationUrl();

        // Redirect to AAD sign in page
        return redirect()->away($authUrl);
    }

    /**
     * @throws Throwable
     */
    public function callback(Request $request, string $client): Redirector|Application|RedirectResponse
    {
        LaravelSocialIntegration::service($client)->auth()->storeToken($request);
        return redirect('/');
    }

    public function logout(): Redirector|Application|RedirectResponse
    {
        app('laravel-outlook')->clearTokens();

        return redirect('/');
    }
}
