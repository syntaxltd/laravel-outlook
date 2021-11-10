<?php


namespace Syntax\LaravelSocialIntegration\Modules\gmail;

use App\Models\PartnerUser;
use Carbon\Carbon;
use Exception;
use Google_Service_Gmail;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
        $this->setState(base64_encode(tenant('id')));
        return $this->createAuthUrl();
    }

    /**
     * @throws Exception
     */
    public function storeToken(Request $request): void
    {
            $code = (string) $request->input('code', null);
            $state = (string) base64_decode($request->input('state', null));

            if (!is_null($code) && !empty($code)) {
                $accessToken = $this->fetchAccessTokenWithAuthCode($code);
                parent::setAccessToken($accessToken);
                Log::info($accessToken);
                if(!is_null($state) && !empty($state)) {
                    //Initialize tenant
                    tenancy()->initialize($state);

                    /**
                     * @var PartnerUser
                     */
                    $user = $this->guard()->user();
                    SocialAccessToken::updateOrCreate(
                        ['partner_user_id' => $user->id],
                        [
                            'access_token' => $accessToken['access_token'],
                            'refresh_token' => $accessToken['refresh_token'],
                            'expires_in' => $accessToken['expires_in'],
                            'type' => 'gmail'
                        ]);
                }

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

    /**
     * Get the guard to be used during authentication.
     *
     * @return StatefulGuard
     */
    protected function guard(): StatefulGuard
    {
        return Auth::guard('partneruser');
    }

}