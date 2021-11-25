<?php


namespace Syntax\LaravelMailIntegration\Modules\gmail;

use App\Models\PartnerUser;
use Exception;
use Google\Service\Gmail\Message;
use Google_Service_Gmail_Message;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Safe\Exceptions\UrlException;
use Syntax\LaravelMailIntegration\Contracts\MailClient;
use Syntax\LaravelMailIntegration\Models\Mail;
use Syntax\LaravelMailIntegration\Modules\gmail\services\GmailConnection;
use Syntax\LaravelMailIntegration\Modules\gmail\services\GmailMessages;
use Throwable;

class LaravelGmail extends GmailConnection implements MailClient
{
    public function __construct(string $userId = null)
    {
        parent::__construct($userId);
    }

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
     * @throws UrlException|Exception
     */
    public function checkReplies(Collection $mails, string $token): Collection
    {

        // Get unique thread ids
        $unique = $mails->unique('thread_id')->toArray();
        collect($unique)->each(function ($email) use ($token, $mails) {
            $response = $this->service->users_threads->get('me', $email['thread_id']);
            $threads = $response->getMessages();
            foreach ($threads as $thread) {
                $mail = new GmailMessages($thread);
                if(!$mails->contains('history_id', $mail->getHistoryId()) && $mail->getHtmlBody()) {
                    /** @var Mail $reply */
                    $reply = Mail::query()->create([
                        'history_id' => $mail->getHistoryId(),
                        'parentable_id' => $email['parentable_id'],
                        'parentable_type' => $email['parentable_type'],
                        'thread_id' => $mail->threadId,
                        'token_id' => $token,
                        'email_id' => $mail->id,
                        'created_at' => $mail->internalDate,
                        'updated_at' => $mail->internalDate,
                        'data' => [
                            'contact' => $email['data']['contact'],
                            'from' => $mail->getFrom(),
                            'to' => $mail->getTo(),
                            'subject' => $mail->subject,
                            'content' => $mail->getHtmlBody(),
                        ],
                    ]);
                    $this->saveAssociations($reply, $email['associations']);
                    $reply->saveAttachments($mail->getAttachments());
                    $mails->add($reply);
                }
            }
        });

        return $mails;
    }

    /**
     * Save note associations.
     *
     * @param Mail $mail
     * @param array $associations
     */
    public function saveAssociations(Mail $mail, array $associations): void
    {
        $mail->contacts()->sync($this->convertToArray($associations['contacts']));
        $mail->companies()->sync($this->convertToArray($associations['companies']));
        $mail->properties()->sync($this->convertToArray($associations['properties']));
        $mail->deals()->sync($this->convertToArray($associations['deals']));
    }

    private function convertToArray(Collection $values): array
    {
        return $values->filter()->map(function ($item) {
            return $item['id'];
        })->toArray();
    }
    public function delete(Mail $mail): Message
    {
       return $this->service->users_messages->trash('me', $mail->email_id);
    }
}
