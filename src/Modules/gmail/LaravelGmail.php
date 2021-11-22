<?php


namespace Syntax\LaravelMailIntegration\Modules\gmail;

use App\Models\Contact;
use App\Models\PartnerUser;
use Exception;
use Google\Service\Gmail\Message;
use Google_Service_Gmail;
use Google_Service_Gmail_Message;
use Google_Service_Gmail_WatchRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Syntax\LaravelMailIntegration\Contracts\MailClient;
use Syntax\LaravelMailIntegration\Models\Mail;
use Syntax\LaravelMailIntegration\Modules\gmail\services\GmailConnection;
use Syntax\LaravelMailIntegration\Modules\gmail\services\GmailMessages;
use Throwable;

class LaravelGmail extends GmailConnection implements MailClient
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

        $mail = new GmailMessages();
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
            'from' => $mail->getFrom(),
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
        $mail = new GmailMessages($this->get($request->input('email_id')));
        $mail->to($this->getContacts($request));
        $mail->cc($request->input('cc'));
        $mail->bcc($request->input('bcc'));
        $mail->subject($request->input('subject'));
        $mail->message($request->input('content'));

        if (!is_null($request->input('attachments'))) {
            $mail->attach($request->input('attachments'));
        }
        $mail->reply();

        return [
            'email_id' => $mail->id,
            'thread_id' => $mail->threadId,
            'history_id' => $this->get($mail->id)->getHistoryId(),
            'subject' => $mail->subject,
            'from' => $mail->getFrom(),
            'to' => $mail->getTo(),
            'message' => $mail->message,
        ];
    }

    /**
     * @throws Exception
     */
    public function checkReplies(Contact $contact, Collection $mails, string $token): Collection
    {
        // Get unique thread ids
        $unique = $mails->unique('thread_id')->pluck('thread_id')->toArray();
        collect($unique)->each(function ($email) use ($token, $contact, $mails) {
            $response = $this->service->users_threads->get('me', $email);
            $threads = $response->getMessages();
            foreach ($threads as $thread) {
                $mail = new GmailMessages($thread);
                if(!$mails->contains('history_id', $mail->getHistoryId()) && $mail->getHtmlBody()) {
                    /** @var Mail $reply */
                    $reply = Mail::query()->create([
                        'history_id' => $mail->getHistoryId(),
                        'parentable_id' => $contact->id,
                        'parentable_type' => get_class($contact),
                        'thread_id' => $mail->threadId,
                        'token_id' => $token,
                        'email_id' => $mail->id,
                        'created_at' => $mail->internalDate,
                        'updated_at' => $mail->internalDate,
                        'data' => [
                            'contact' => [[
                                'id' => $contact->id,
                                'name' => $contact->name,
                                'email' => $contact->email,
                            ]],
                            'from' => $mail->getFrom(),
                            'to' => $mail->getTo(),
                            'subject' => $mail->subject,
                            'content' => $mail->getHtmlBody(),
                        ],
                    ]);
                    $reply->saveAttachments($mail->getAttachments());
                    $mails->add($reply);
                }
            }
        });

        return $mails;
    }


    /**
     * users.stop receiving push notifications for the given user mailbox.
     *
     * @param  string  $userEmail  Email address
     * @param  array  $optParams
     * @return Google_Service_Gmail
     */
    public function stopWatch(string $userEmail, array $optParams = []): Google_Service_Gmail
    {
        $service = new Google_Service_Gmail($this);

        return $service->users->stop($userEmail, $optParams);
    }

    /**
     * Set up or update a push notification watch on the given user mailbox.
     *
     * @return array
     */
    public function setWatch(): array
    {
        $projectId = config('laravel-mail-integration.services.gmail.project_id');
        $rq = new Google_Service_Gmail_WatchRequest();
        $rq->setTopicName('projects/'.$projectId.'/topics/mykii');
        $history = $this->service->users->watch('me', $rq);
        $historyList = $this->service->users_history->listUsersHistory('me', [
            'startHistoryId' => $history->historyId
        ]);
        $mails = [];
        foreach ($historyList->history as $chunk) {
            foreach ($chunk->messages as $msg) {
                $mails[] = $msg;
            }
        }

        return $mails;
    }

    public function delete(Mail $mail): Message
    {
       return $this->service->users_messages->trash('me', $mail->email_id);
    }
}
