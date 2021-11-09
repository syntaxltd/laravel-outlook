<?php


namespace Syntax\LaravelSocialIntegration\Modules\gmail;


use App\Models\PartnerUser;
use Carbon\Carbon;
use Google_Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Syntax\LaravelSocialIntegration\Models\SocialAccessToken;
use Syntax\LaravelSocialIntegration\Modules\gmail\traits\Configurable;

class LaravelGmail extends Google_Client
{
    use Configurable {
        Configurable::__construct as configConstruct;
    }
    protected $token;

    public function __construct()
    {
        parent::__construct($this->getConfigs());

        $this->configApi();
    }

    /**
     * Gets the URL to authorize the user
     *
     * @return string
     */
    public function getOAuthClient()
    {
        return $this->createAuthUrl();
    }
    /**
     * @return array|string
     * @throws \Exception
     */
    public function makeToken()
    {
        if (parent::isAccessTokenExpired()) {
            $request = Request::capture();
            $code = (string) $request->input('code', null);
            if (!is_null($code) && !empty($code)) {
                $accessToken = $this->fetchAccessTokenWithAuthCode($code);
                parent::setAccessToken($accessToken);
                $this->storeTokens($accessToken);
                return $accessToken;
            } else {
                throw new \Exception('No access token');
            }
        } else {
            return $this->getAccessToken();
        }
    }

    /**
     * Save received access tokens from social account.
     *
     * @param array $accessToken
     *
     * @return void
     */
    public function storeTokens(array $accessToken): void
    {
        SocialAccessToken::updateOrCreate(
            ['partner_user_id' => Auth::id()],
            [
                'access_token' => $accessToken['access_token'],
                'refresh_token' => $accessToken['refresh_token'],
                'expires_at' => Carbon::parse(now())->addSeconds($accessToken['expires_in'])
            ]);
    }

}