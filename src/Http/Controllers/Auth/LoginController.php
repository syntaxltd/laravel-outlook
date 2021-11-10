<?php

namespace Syntax\LaravelSocialIntegration\Http\Controllers\Auth;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Response;
use Syntax\LaravelSocialIntegration\Http\Controllers\Controller;
use Syntax\LaravelSocialIntegration\LaravelSocialIntegration;
use Throwable;

class LoginController extends Controller
{
    /**
     * @throws Throwable
     */
    public function login(string $client): JsonResponse
    {
        $authUrl = LaravelSocialIntegration::service($client)->auth()->getAuthorizationUrl();
        // Redirect to AAD sign in page
        return Response::json([
            'link' => $authUrl
        ]);
    }

    /**
     * @throws Throwable
     */
    public function callback(Request $request, string $client): Redirector|Application|RedirectResponse
    {
        LaravelSocialIntegration::service($client)->auth()->storeToken($request);
        return Redirect::to('https://'.tenant()->primary_domain.':8081/');
    }

    /**
     * @throws Throwable
     */
    public function logout(string $client): Redirector|Application|RedirectResponse
    {
        LaravelSocialIntegration::service($client)->auth()->clearTokens();

        return Redirect::to('https://'.tenant()->primary_domain.':8081/');
    }
}
