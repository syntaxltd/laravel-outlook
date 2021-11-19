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
        $mail->to($this->getContacts($request));
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
            'from' => $mail->getFrom()['name'] ?: $mail->getFrom()['email'],
            'to' => $mail->getTo(),
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
            'from' => $mail->getFrom()['name'] ?: $mail->getFrom()['email'],
            'to' => $mail->getTo(),
            'message' => $mail->message,
        ];
    }

    /**
     * @throws Exception
     */
    public function all(): Collection
    {
        $mails = SocialAccessMail::query()->distinct()->get(['thread_id']);
        $mails->each(function (SocialAccessMail $email) {
            $response = $this->service->users_threads->get('me', $email->thread_id);
            $threads = $response->getMessages();
            foreach ($threads as $thread) {
                /** @var SocialAccessMail $socialMail */
                $socialMail = SocialAccessMail::query()->firstWhere('thread_id', $thread->threadId);
                $mail = new Mail($thread);
                if ($mail->getHtmlBody()) {
                    SocialAccessMail::query()->firstOrCreate(['history_id' => $mail->getHistoryId()], [
                        'parentable_id' => $socialMail->parentable_id,
                        'parentable_type' => $socialMail->parentable_type,
                        'thread_id' => $mail->threadId,
                        'token_id' => $socialMail->token_id,
                        'email_id' => $mail->id,
                        'created_at' => $mail->internalDate,
                        'updated_at' => $mail->internalDate,
                        'data' => [
                            'contact' => $socialMail->data['contact'],
                            'from' => $mail->getFrom()['name'] ?: $mail->getFrom()['email'],
                            'to' => $mail->getTo(),
                            'subject' => $mail->subject,
                            'content' => $mail->getHtmlBody(),
                        ],
                    ]);
                }
            }
        });

        return collect([]);
    }

}
