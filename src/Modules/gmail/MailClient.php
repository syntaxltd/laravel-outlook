<?php


namespace Syntax\LaravelSocialIntegration\Modules\gmail;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Syntax\LaravelSocialIntegration\Models\SocialAccessEmail;
use Exception;
use Google_Service_Gmail_Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Syntax\LaravelSocialIntegration\Contracts\SocialClient;
use Syntax\LaravelSocialIntegration\Models\SocialAccessToken;
use Syntax\LaravelSocialIntegration\Modules\gmail\services\GmailConnection;
use Syntax\LaravelSocialIntegration\Modules\gmail\services\Mail;

class MailClient extends GmailConnection implements SocialClient
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param string $id
     *
     * @return Google_Service_Gmail_Message
     */
    public function get(string $id): Google_Service_Gmail_Message
    {
        return $this->service->users_messages->get('me', $id);
    }

    public function all()
    {
        $client = SocialAccessToken::Where('partner_user_id', Auth::id())->where('type', 'gmail')->pluck('id');
        return SocialAccessEmail::whereIn('social_access_token_id', $client)->get();
    }

    /**
     * Sends a new email
     *
     * @param Request $request
     * @return Mail
     * @throws Exception
     */
    public function send(Request $request): Mail
    {
        $mail = new Mail();
        $mail->to($this->getContacts($request));
        $mail->from(Auth::user()->email, Auth::user()->name);
        $mail->cc($request->input('cc'));
        $mail->bcc($request->input('bcc'));
        $mail->subject($request->input('subject'));
        $mail->message($request->input('message'));
        $mail->attach($request->input('attachments'));
        $mail->send();

        $this->storeEmail($mail);
        return $mail;
    }

    private function getContacts(Request $request): array
    {
        return collect($request->input('contact'))->filter()->map(function ($item) {
            return $item['email'];
        })->toArray();
    }


    private function storeEmail(Mail $mail): SocialAccessEmail
    {
        $email = new SocialAccessEmail;
        $email->email_id = $mail->id;
        $email->thread_id = $mail->threadId;
        $email->social_access_token_id = SocialAccessToken::Where('partner_user_id', Auth::id())->where('type', 'gmail')->first()->id;
        $email->data = json_encode($mail);
        $email->save();

       return $email->refresh();
    }

}