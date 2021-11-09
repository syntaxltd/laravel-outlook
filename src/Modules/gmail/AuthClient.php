<?php


namespace Syntax\LaravelSocialIntegration\Modules\gmail;

use Carbon\Carbon;
use Exception;
use Google_Service_Gmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Syntax\LaravelSocialIntegration\Contracts\SocialClientAuth;
use Syntax\LaravelSocialIntegration\Models\SocialAccessToken;
use Syntax\LaravelSocialIntegration\Modules\gmail\traits\Configurable;

class AuthClient extends \Google_Client implements SocialClientAuth
{
    use Configurable;

    public function __construct()
    {
        parent::__construct($this->getConfigs());

        $this->configApi();
    }

    public function getAuthorizationUrl(): string
    {
        return $this->getOAuthClient();
    }

    /**
     * Gets the URL to authorize the user
     *
     * @return string
     */
    public function getOAuthClient(): string
    {
        return $this->createAuthUrl();
    }

    /**
     * @throws Exception
     */
    public function storeToken(Request $request): void
    {
            $code = (string) $request->input('code', null);
            if (!is_null($code) && !empty($code)) {
                $accessToken = $this->fetchAccessTokenWithAuthCode($code);
                parent::setAccessToken($accessToken);

                SocialAccessToken::updateOrCreate(
                    ['partner_user_id' => Auth::id()],
                    [
                        'access_token' => $accessToken['access_token'],
                        'refresh_token' => $accessToken['refresh_token'],
                        'expires_in' => $accessToken['expires_in']
                    ]);

            } else {
                throw new Exception('No access token');
            }
    }

    public function clearTokens(): void
    {
        $this->revokeToken();

        // Change to get Social Access Token for authenticated users
        SocialAccessToken::take(1)->delete();
    }
}