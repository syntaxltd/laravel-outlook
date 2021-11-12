<?php


namespace Syntax\LaravelSocialIntegration\Modules\gmail;

use App\Models\PartnerUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Syntax\LaravelSocialIntegration\Contracts\SocialClientAuth;
use Syntax\LaravelSocialIntegration\Exceptions\InvalidStateException;
use Syntax\LaravelSocialIntegration\Models\SocialAccessToken;
use Syntax\LaravelSocialIntegration\Modules\gmail\traits\Configurable;
use Throwable;
use function Safe\base64_decode;

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

        Log::info($code);
        throw_if(is_null($code), new InvalidStateException('No access token.'));

         $accessToken = $this->fetchAccessTokenWithAuthCode($code);

         parent::setAccessToken($accessToken);
         SocialAccessToken::query()->updateOrCreate(
             [
                 'partner_user_id' => Auth::id(),
                 'type' => 'gmail',
             ], [
             'access_token' => $accessToken['access_token'],
             'refresh_token' => $accessToken['refresh_token'],
             'expires_in' => $accessToken['expires_in'],
         ]);

    }

    public function clearTokens(): void
    {
        $this->revokeToken();

        // Change to get Social Access Token for authenticated users
        SocialAccessToken::Where('partner_user_id', Auth::id())->where('type', 'gmail')->delete();
    }
}
