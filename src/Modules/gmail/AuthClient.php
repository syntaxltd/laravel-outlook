<?php


namespace Syntax\LaravelSocialIntegration\Modules\gmail;

use Google_Service_Gmail;
use Google_Service_Gmail_Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Syntax\LaravelSocialIntegration\Contracts\SocialClientAuth;
use Syntax\LaravelSocialIntegration\Exceptions\InvalidStateException;
use Syntax\LaravelSocialIntegration\Models\SocialAccessToken;
use Syntax\LaravelSocialIntegration\Modules\gmail\traits\Configurable;
use Throwable;

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
        $this->setState(tenant('id'));

        return $this->createAuthUrl();
    }

    /**
     * @throws Throwable
     */
    public function storeToken(Request $request): void
    {
        /** @var string|null $code */
        $code = $request->input('code');

        throw_if(is_null($code), new InvalidStateException('No access token.'));

        $accessToken = $this->fetchAccessTokenWithAuthCode($code);

        parent::setAccessToken($accessToken);
        $me = $this->getProfile();
        if (property_exists($me, 'emailAddress')) {
            $accessToken['email'] = $me->emailAddress;
        }

        SocialAccessToken::query()->updateOrCreate([
            'partner_user_id' => auth('partneruser')->id(),
            'type' => 'gmail',
        ], [
            'access_token' => $accessToken['access_token'],
            'refresh_token' => $accessToken['refresh_token'],
            'expires_in' => $accessToken['expires_in'],
            'email' => $accessToken['email']
        ]);
    }

    /**
     * Gets user profile from Gmail
     *
     * @return Google_Service_Gmail_Profile
     */
    public function getProfile(): Google_Service_Gmail_Profile
    {
        $service = new Google_Service_Gmail($this);

        return $service->users->getProfile('me');
    }

    public function clearTokens(): void
    {
        $this->revokeToken();

        // Change to get Social Access Token for authenticated users
        SocialAccessToken::Where('partner_user_id', Auth::id())->where('type', 'gmail')->delete();
    }
}
