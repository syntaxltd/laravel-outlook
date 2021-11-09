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
use Syntax\LaravelOutlook\Exceptions\InvalidStateException;
use Syntax\LaravelOutlook\Http\Controllers\Controller;
use Throwable;

class LoginController extends Controller
{
    public function login(): RedirectResponse
    {
        $oauthClient = app('laravel-outlook')->getOAuthClient();

        $authUrl = $oauthClient->getAuthorizationUrl();

        // Save client state so we can validate in callback
        session(['oauthState' => $oauthClient->getState()]);

        // Redirect to AAD sign in page
        return redirect()->away($authUrl);
    }

    /**
     * @throws Throwable
     */
    public function callback(Request $request): Redirector|Application|RedirectResponse
    {
        // Validate the state
        $expectedState = session('oauthState');
        $request->session()->forget('oauthState');
        $providedState = $request->query('state');

        if (!isset($expectedState)) {
            // If there is no expected state in the session,
            // do nothing and redirect to the home page.
            return redirect('/test');
        }

        throw_if(!isset($providedState) || $expectedState != $providedState, new InvalidStateException());

        // Authorization code should be in the "code" query param
        $authCode = $request->query('code');
        if (isset($authCode)) {
            try {
                /**
                 * @var AccessToken $accessToken
                 */
                $accessToken = app('laravel-outlook')->getOAuthClient()->getAccessToken('authorization_code', [
                    'code' => $authCode,
                ]);

                $graph = (new Graph)->setAccessToken($accessToken->getToken());

                $user = $graph->createRequest('GET', '/me?$select=displayName,mail,mailboxSettings,userPrincipalName')
                    ->setReturnType(User::class)
                    ->execute();

                app('laravel-outlook')->storeTokens($accessToken, $user);

                return redirect('/');
            } catch (IdentityProviderException $exception) {
                return redirect('/')
                    ->with('error', 'Error requesting access token')
                    ->with('errorDetail', json_encode($exception->getResponseBody()));
            }
        }

        return redirect('/')
            ->with('error', $request->query('error'))
            ->with('errorDetail', $request->query('error_description'));
    }

    public function logout(): Redirector|Application|RedirectResponse
    {
        app('laravel-outlook')->clearTokens();

        return redirect('/');
    }
}
