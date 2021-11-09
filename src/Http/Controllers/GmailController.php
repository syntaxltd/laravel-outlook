<?php


namespace Syntax\LaravelSocialIntegration\Http\Controllers;

use App\Models\PartnerUser;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class GmailController extends Controller
{

    public function __construct()
    {
    }

    /**
     * Display a listing of the resource.
     *
     * @param string|null $pageToken
     * @throws InvalidStateException|\Google_Exception
     */
    public function index(string $pageToken = null)
    {

        /** @var PartnerUser $partner */
        $partner = PartnerUser::find(Auth::id());
        return response()->json([
            'messages' => (new LaravelGmail())->setUserId($partner->global_id)->getMessages()->unread()->preload()->all()
        ]);
    }

    /**
     * Add the 'TRASH' label to the email.
     *
     * @param int|null $id
     * @return Application|RedirectResponse|Redirector
     */
    public function trash(int $id = null): Application|RedirectResponse|Redirector
    {

        $filtered = LaravelGmail::getMessages()->get($id);
        $filtered->sendToTrash();

        if (in_array('TRASH', $filtered->getLabels())) {
            return redirect()->back()->with('status', 'Email has been deleted');
        } else {
            return redirect()->back()->with('status', 'Email could not deleted');
        }
    }

    /**
     * Remove the 'UNREAD' label from the email.
     *
     * @param int|null $id
     * @return Application|RedirectResponse|Redirector
     */
    public function markAsRead(int $id = null): Application|RedirectResponse|Redirector
    {

        $filtered = LaravelGmail::message()->get($id);
        $filtered->markAsRead();

        if (!in_array('UNREAD', $filtered->getLabels())) {
            return redirect()->back()->with('status', 'Email has been marked as read');
        } else {
            return redirect()->back()->with('status', 'Email could not update');
        }
    }

    /**
     * Add the 'UNREAD' label to the email.
     *
     * @param int|null $id
     * @return Application|RedirectResponse|Redirector
     */
    public function markAsUnread(int $id = null): Application|RedirectResponse|Redirector
    {
        $filtered = LaravelGmail::message()->get($id);
        $filtered->markAsUnread();

        if (in_array('UNREAD', $filtered->getLabels())) {
            return redirect()->back()->with('status', 'Email has been marked as unread');
        } else {
            return redirect()->back()->with('status', 'Email could not update');
        }
    }

    /**
     * Display a specific email details.
     *
     * @param int|null $id
     * @return View
     */
    public function detail(int $id = null): View
    {
        $filtered = LaravelGmail::message()->get($id);
        return view('mail-details', compact('filtered'));
    }

    /**
     * Create and send an email.
     *
     * @param Request $request
     * @return Application|RedirectResponse|Redirector
     */
    public function create(Request $request): Application|RedirectResponse|Redirector
    {
        $mail = new Mail;
        $mail->to($request->email);
        $mail->from(LaravelGmail::user());
        $mail->subject($request->subject);
        $mail->message($request->message);
        $mail->send();

        return redirect()->back()->with('status', 'Email has been sent');
    }
}
