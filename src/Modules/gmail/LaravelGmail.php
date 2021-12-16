<?php


namespace Syntax\LaravelMailIntegration\Modules\gmail;

use App\Models\CentralMail;
use App\Models\Contact;
use App\Models\PartnerUser;
use Exception;
use Google\Service\Gmail\ListHistoryResponse;
use Google_Service_Gmail_Message;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
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
     * @return array
     * @throws Throwable
     */
    public function send(Request $request): array
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

        return [
            'email_id' => $mail->getId(),
            'thread_id' => $mail->getThreadId(),
            'history_id' => $this->get($mail->getId())->getHistoryId(),
            'subject' => $mail->subject,
            'from' => [
                'name' => $user->name,
                'address' => $mail->getFrom()['name'],
            ],
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
     * @throws \Google\Exception
     */
    public function get(string $id): Google_Service_Gmail_Message
    {
        $responseOrRequest = $this->service->users_messages->get('me', $id);

        if (get_class($responseOrRequest) === "GuzzleHttp\Psr7\Request") {
            return $this->service->getClient()->execute($responseOrRequest,
                'Google_Service_Gmail_Message');
        }

        return $responseOrRequest;
    }

    /**
     * @param string $id
     *
     * @return ListHistoryResponse
     * @throws \Google\Exception
     */
    public function listHistory(string $id): ListHistoryResponse
    {
        $responseOrRequest = $this->service->users_history->listUsersHistory('me', [
            'startHistoryId' => $id,
            'labelId' => ['UNREAD', 'INBOX']
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
    public function checkReplies(Request $request, MailAccessToken $accessToken): Collection
    {
        $mails = Mail::query()->where('token_id', $accessToken->id)->get();
        $data = $this->decodeMessageBody($request);
        if(count($mails) > 0) {
            $historyResponse = $this->listHistory($mails->last()->history_id);
        }else{
            $historyResponse = $this->listHistory($data['history_id']);
        }

        if ($historyResponse->getHistory()) {
            foreach ($historyResponse->getHistory() as $messages) {
                foreach ($messages->getMessages() as $message){
                    $mail = new GmailMessages($this->get($message->id));
                    if(!$mails->contains('email_id', $message->id) && $mail->getHtmlBody()) {
                        if ($mails->contains('thread_id', $message->threadId)) {
                            /**
                             * @var Mail $threadMail
                             */
                            $threadMail = $mails->where('thread_id', $message->threadId)->first();
                            $email = $threadMail->append('associations');

                            /**
                             * @var Mail $reply
                             */
                            $reply = Mail::query()->create([
                                'history_id' => $mail->getHistoryId(),
                                'parentable_id' => $email['parentable_id'],
                                'parentable_type' => $email['parentable_type'],
                                'thread_id' => $mail->threadId,
                                'token_id' => $accessToken->id,
                                'email_id' => $mail->id,
                                'created_at' => $mail->internalDate,
                                'updated_at' => $mail->internalDate,
                                'content' => $mail->getHtmlBody(),
                                'data' => [
                                    'from' => $mail->getFrom(),
                                    'to' => $mail->getTo(),
                                    'subject' => $mail->subject,
                                    'content' => $mail->getHtmlBody(),
                                ],
                            ]);

                            $this->saveAssociations($reply, $email['associations']);
                            $reply->saveAttachments($mail->getAttachments());
                            $mails->add($reply);
                        } else {
                            $existingContact = Contact::where('email', 'LIKE', $mail->from)->first();
                            if($existingContact){
                                /**
                                 * @var Mail $reply
                                 */
                                $reply = Mail::query()->create([
                                    'history_id' => $mail->getHistoryId(),
                                    'parentable_id' => $existingContact->id,
                                    'parentable_type' => 'App\Models\Contact',
                                    'thread_id' => $mail->threadId,
                                    'token_id' => $accessToken->id,
                                    'email_id' => $mail->id,
                                    'created_at' => $mail->internalDate,
                                    'updated_at' => $mail->internalDate,
                                    'content' => $mail->getHtmlBody(),
                                    'data' => [
                                        'from' => $mail->getFrom(),
                                        'to' => $mail->getTo(),
                                        'subject' => $mail->subject,
                                        'content' => $mail->getHtmlBody(),
                                    ],
                                ]);

                                $reply->contacts()->sync([$existingContact->id]);
                                $reply->saveAttachments($mail->getAttachments());
                                $mails->add($reply);
                            }
                        }
                    }
                }
            }
        }

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
