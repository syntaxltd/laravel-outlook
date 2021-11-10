<?php


namespace Syntax\LaravelSocialIntegration\Http\Controllers;

use App\Models\PartnerUser;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Syntax\LaravelSocialIntegration\LaravelSocialIntegration;
use Syntax\LaravelSocialIntegration\Models\SocialAccessToken;

class MailController extends Controller
{
    /**
     * Get all social providers for authenticated user.
     *
     * @return JsonResponse
     */
    public function provider(): JsonResponse
    {
        return Response::json([
            'provider' => SocialAccessToken::Where('partner_user_id', Auth::id())
        ]);
    }

    /**
     * Create and send an email.
     *
     * @param Request $request
     * @param string $client
     * @return Application|RedirectResponse|Redirector
     */
    public function create(Request $request,string $client): Application|RedirectResponse|Redirector
    {
        dd(LaravelSocialIntegration::service($client)->messages()->send($request));
        return redirect()->back()->with('status', 'Email has been sent');
    }
}
