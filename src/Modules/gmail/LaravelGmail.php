<?php


namespace Syntax\LaravelMailIntegration\Modules\gmail;

use App\Models\CentralMail;
use App\Models\Contact;
use App\Models\PartnerUser;
use Exception;
use Google\Service\Gmail\ListHistoryResponse;
use Google_Service_Gmail_Message;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Safe\Exceptions\JsonException;
use Safe\Exceptions\UrlException;
use Syntax\LaravelMailIntegration\Contracts\MailClient;
use Syntax\LaravelMailIntegration\Models\Mail;
use Syntax\LaravelMailIntegration\Models\MailAccessToken;
use Syntax\LaravelMailIntegration\Modules\gmail\services\GmailConnection;
use Syntax\LaravelMailIntegration\Modules\gmail\services\GmailMessages;
use Throwable;
use function Safe\base64_decode;
use function Safe\json_decode;

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
     * @return void
     * @throws Throwable
     */
    public function send(Request $request): void
    {
        /**
         * @var PartnerUser $user
         */
        $user = auth('partneruser')->user();

        $mail = new GmailMessages();
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

        if(Mail::query()->count() === 0) {
            /**
             * @var Contact $contact
             */
            $contact = Contact::find($request->input('parentable_id'));
            /**
             * @var MailAccessToken $token
             */
            $token = MailAccessToken::query()->where(['partner_user_id' => auth()->id(), 'type' => 'gmail'])->first();

            $mailData = new GmailMessages($this->get($mail->getId()));
            $this->saveMessage($mailData, $contact, $token);
        }
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
     * @return Google_Service_Gmail_Message|null
     * @throws \Google\Exception
     */
    public function get(string $id): ?Google_Service_Gmail_Message
    {
        try {
            $responseOrRequest = $this->service->users_messages->get('me', $id);

            if (get_class($responseOrRequest) === "GuzzleHttp\Psr7\Request") {
                return $this->service->getClient()->execute($responseOrRequest,
                    'Google_Service_Gmail_Message');
            }

            return $responseOrRequest;
        } catch (\Google\Service\Exception) {
            return  null;
        }

    }

    /**
     * @param ?string $id
     *
     * @return ListHistoryResponse
     * @throws \Google\Exception
     */
    public function listHistory(?string $id): ListHistoryResponse
    {
        $responseOrRequest = $this->service->users_history->listUsersHistory('me', [
            'startHistoryId' => $id,
            'labelId' => ['UNREAD', 'INBOX'],
            'historyTypes' => 'messageAdded'
        ]);
        if (get_class($responseOrRequest) === "GuzzleHttp\Psr7\Request") {
            return $this->service->getClient()->execute($responseOrRequest,
                'Google\Service\Gmail\ListHistoryResponse');
        }

        return $responseOrRequest;
    }

    /**
     * Sends a new email
     *
     * @param Request $request
     * @return void
     * @throws Throwable
     */
    public function reply(Request $request): void
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
    }

    /**
     * @throws UrlException|Exception
     */
    public function checkReplies(Request $request, MailAccessToken $accessToken): bool
    {
        $mails = Mail::query()->where('token_id', $accessToken->id)->get();
        if($mails->count() === 0) return false;
        /** @var Mail $lastMail */
        $lastMail = $mails->last();
        $historyResponse = $this->listHistory($lastMail->history_id);
        if ($historyResponse->getHistory()) {
            foreach ($historyResponse->getHistory() as $messages) {
                foreach ($messages->getMessages() as $message){
                    $singleEmail = $this->get($message->id);

                    /** @var GmailMessages|null $mail */
                    $mail = $singleEmail ? new GmailMessages($singleEmail) : null;
                    if (!is_null($mail) && !is_null($mail->getHtmlBody())) {
                        $contact = Contact::query()->where('email', $mail->from)->first();
                        if ($mails->contains('thread_id', $message->threadId)) {
                            /**
                             * @var Mail|null $thread
                             */
                            $thread = Mail::withTrashed()->firstWhere('thread_id', $message->threadId);
                            if (!is_null($thread)) {
                                if(!is_null($thread->deleted_at)) {
                                    $thread->restore();
                                }
                                $contact = $thread->parentable;
                            }

                        }

                        if (!is_null($contact)) {
                            $trashedMail = Mail::withTrashed()->firstWhere('email_id', $mail->id);
                            if(is_null($trashedMail)) {
                                /**
                                 * @var Mail $reply
                                 */
                                $reply = $this->saveMessage($mail, $contact, $accessToken);
                                $reply->saveAttachments($mail->getAttachments());
                                $mails->add($reply);
                            }
                        }
                    }
                }
            }
        }
        return true;
    }

    /**
     * @throws Exception
     */
    private function saveMessage (GmailMessages $mail, Contact $contact, MailAccessToken $accessToken): Model
    {
        return Mail::query()->create([
            'history_id' => $mail->getHistoryId(),
            'parentable_id' => $contact->id,
            'parentable_type' => get_class($contact),
            'thread_id' => $mail->threadId,
            'token_id' => $accessToken->id,
            'email_id' => $mail->id,
            'created_at' => $mail->internalDate,
            'updated_at' => $mail->internalDate,
            'content' => $mail->getHtmlBody(),
            'data' => [
                'from' => [
                    'name' => $contact->name,
                    'address' => $mail->getFrom()['name'],
                ],
                'to' => $mail->getTo(),
                'subject' => $mail->subject,
                'content' => $mail->getHtmlBody(),
            ],
        ]);
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

    /**
     * @param Request $request
     * @return Collection
     * @throws JsonException
     * @throws UrlException
     */
    public function getTenant(Request $request): Collection
    {
        $contents = $this->decodeMessageBody($request);
        return CentralMail::query()->where('email', $contents['emailAddress'])->get();
    }

    /**
     * @param Request $request
     * @return array
     * @throws JsonException
     * @throws UrlException
     */
    private function decodeMessageBody(Request $request): array
    {
        $data = collect($request->input('message'))->filter(function ($value, $key) {
            return $key === 'data';
        })
            ->map(function ($item) {
                return $item;
            })->toArray();
        return json_decode(base64_decode($data['data']), true);
    }
}
