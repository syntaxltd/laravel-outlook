<?php


namespace Syntax\LaravelSocialIntegration\Modules\gmail;

use App\Models\PartnerUser;
use Exception;
use Google_Service_Gmail_Message;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Syntax\LaravelSocialIntegration\Contracts\SocialClient;
use Syntax\LaravelSocialIntegration\Models\SocialAccessMail;
use Syntax\LaravelSocialIntegration\Models\SocialAccessToken;
use Syntax\LaravelSocialIntegration\Modules\gmail\services\GmailConnection;
use Syntax\LaravelSocialIntegration\Modules\gmail\services\Mail;
use Throwable;

class LaravelGmail extends GmailConnection implements SocialClient
{
    public function auth(): AuthClient
    {
        return new AuthClient();
    }

    public function all(): Collection
    {
        $client = SocialAccessToken::query()->where('partner_user_id', Auth::id())->where('type', 'gmail')->pluck('id');

        return SocialAccessMail::query()->whereIn('token_id', $client)->get();
    }

    /**
     * Sends a new email
     *
     * @param Request $request
     * @return array
     * @throws Throwable
     */
    public function send(Request $request): array
    {
        /** @var PartnerUser $user */
        $user = auth('partneruser')->user();

        $mail = new Mail();
        $mail->to(['eva.mwangi@synt.ax']);
        $mail->from($user->email, $user->name);
        $mail->cc($request->input('cc'));
        $mail->bcc($request->input('bcc'));
        $mail->subject($request->input('subject'));
        $mail->message($request->input('content'));

        if (!is_null($request->input('attachments'))) {
            $mail->attach($request->input('attachments'));
        }
        $mail->send();

        return [
            'email_id' => $mail->getId(),
            'thread_id' => $mail->getThreadId(),
            'history_id' => $this->get($mail->getId())->getHistoryId(),
            'subject' => $mail->subject,
            'message' => $mail->message,
        ];
    }

    private function getContacts(Request $request): array
    {
        return collect($request->input('contact'))->filter()->map(function ($item) {
            return $item['email'];
        })->toArray();
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

    /**
     * Sends a new email
     *
     * @param Request $request
     * @return array
     * @throws Throwable
     */
    public function reply(Request $request): array
    {
        $mail = new Mail($this->get($request->input('email_id')));
        $mail->to($this->getContacts($request));
        $mail->cc($request->input('cc'));
        $mail->bcc($request->input('bcc'));
        $mail->subject($request->input('subject'));
        $mail->message($request->input('content'));
        $mail->reply();

        return [
            'email_id' => $mail->id,
            'thread_id' => $mail->threadId,
            'history_id' => $this->get($mail->id)->getHistoryId(),
            'subject' => $mail->subject,
            'message' => $mail->message,
        ];
    }

    /**
     * @throws Exception
     */
    public function history(SocialAccessMail $mail): array
    {
        $mails = [];
        $response =  $this->service->users_threads->get('me', $mail->thread_id);
        $allMessages = $response->getMessages();
        foreach ($allMessages as $message) {
            $mailData = new Mail($message);
            if($mailData->getHtmlBody()) {
                $mails[] = [
                    'email_id' => $mailData->id,
                    'thread_id' => $mailData->threadId,
                    'history_id' => $this->get($mailData->id)->getHistoryId(),
                    'subject' => $mailData->subject,
                    'message' => $mailData->getHtmlBody(),
                ];
            }
        }
        return $mails;
    }

}
