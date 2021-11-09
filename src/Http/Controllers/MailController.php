<?php


namespace Syntax\LaravelSocialIntegration\Http\Controllers;

use App\Models\PartnerUser;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Syntax\LaravelSocialIntegration\LaravelSocialIntegration;

class MailController extends Controller
{

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
