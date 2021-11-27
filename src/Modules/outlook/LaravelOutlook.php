<?php

namespace Syntax\LaravelMailIntegration\Modules\outlook;

use App\Models\CentralMail;
use App\Models\Contact;
use App\Models\Partner;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Microsoft\Graph\Exception\GraphException;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model\Attachment;
use Microsoft\Graph\Model\ChatMessage;
use Microsoft\Graph\Model\Subscription;
use Safe\Exceptions\FilesystemException;
use Syntax\LaravelMailIntegration\Contracts\MailClient;
use Syntax\LaravelMailIntegration\Models\Mail;
use Syntax\LaravelMailIntegration\Models\MailAccessToken;
use Syntax\LaravelMailIntegration\Modules\outlook\messages\Message;
use Throwable;
use function Safe\base64_decode;

class LaravelOutlook implements MailClient
{
    public string $id;

    public function __construct(string $id)
    {
        $this->id = $id;
    }

    /**
     * @param string $id
     * @throws GraphException
     * @throws GuzzleException
     * @throws Throwable
     */
    public function unsubscribe(string $id): void
    {
        $subscription = $this->getGraphClient()
            ->createRequest('GET', "/subscriptions/$id")
            ->execute();
        info('Subscription', [$subscription]);
    }

    /**
     * @throws Throwable
     */
    public function getGraphClient(): Graph
    {
        return (new Graph)->setAccessToken($this->auth()->getToken());
    }

    /**
     * Init auth class
     *
     * @return AuthClient
     */
    public function auth(): AuthClient
    {
        return new AuthClient($this->id);
    }

    /**
     * @return Subscription
     * @throws GraphException
     * @throws GuzzleException
     * @throws Throwable
     */
    public function subscribe(): Subscription
    {
        $subscription = $this->getGraphClient()->createRequest('POST', '/subscriptions')->attachBody([
            "changeType" => "updated",
            "notificationUrl" => "https://oopioha1yi.sharedwithexpose.com/partner/oauth/notifications/outlook",
            "resource" => "/me/messages",
            "expirationDateTime" => "2021-11-27T11:00:00.0000000Z",
            "clientState" => "SecretClientState",
        ])->setReturnType(Subscription::class)->execute();
        info('Subscription', [$subscription]);

        return $subscription;
    }

    /**
     * @throws Throwable
     * @throws GraphException
     */
    public function checkReplies(Request $request, MailAccessToken $token): void
    {
        collect($request->input('value'))->each(function ($change) use ($token) {
            info('Notification received', $change);

            $emailId = $change['resourceData']['id'];
            /** @var Mail|null $existingMessage */
            $existingMessage = Mail::query()->firstWhere('email_id', $emailId);
            if (is_null($existingMessage)) {
                $message = $this->getMail($emailId);
                $properties = $message->getProperties();

                $thread = Mail::query()->firstWhere('thread_id', $properties['conversationId']);
                if (!is_null($thread)) {
                    /** @var Mail $reply */
                    $reply = $this->saveReply($message, $token->id, $thread->parentable);
                    if ($properties['hasAttachments']) {
                        $reply->saveAttachments($this->getAttachments($emailId));
                    }
                }
            }
        });
    }

    /**
     * @param string $id
     * @return ChatMessage
     * @throws GraphException
     * @throws GuzzleException
     * @throws Throwable
     */
    private function getMail(string $id): ChatMessage
    {
        return $this->getGraphClient()
            ->createRequest('GET', "/me/messages/$id")
            ->setReturnType(ChatMessage::class)
            ->execute();
    }

    protected function saveReply(ChatMessage $message, int $token, Contact $contact): Model|Builder
    {
        $properties = $message->getProperties();
        $from = $message->getFrom()?->getProperties();

        return Mail::query()->create([
            'email_id' => $message->getId(),
            'parentable_id' => $contact->id,
            'parentable_type' => get_class($contact),
            'thread_id' => $properties['conversationId'],
            'token_id' => $token,
            'created_at' => $properties['createdDateTime'],
            'updated_at' => $properties['lastModifiedDateTime'],
            'data' => [
                'contact' => [[
                    'id' => $contact->id,
                    'name' => $contact->name,
                    'email' => $contact->email,
                ]],
                'from' => $from ? $from['emailAddress'] : [],
                'content' => $properties['body']['content'],
                'subject' => $properties['subject'],
                'bodyPreview' => $properties['bodyPreview'],
                'to' => collect($properties['toRecipients'])->map(fn($recipient) => $recipient['emailAddress'])->toArray()
            ],
        ]);
    }

    /**
     * @param string $message
     * @return array
     * @throws GraphException
     * @throws GuzzleException
     * @throws Throwable
     */
    protected function getAttachments(string $message): array
    {
        $files = $this->getGraphClient()
            ->createRequest('GET', "/me/messages/$message/attachments")
            ->setReturnType(Attachment::class)->execute();

        $attachments = [];
        collect($files)->each(function (Attachment $attachment) use (&$attachments) {
            $properties = $attachment->getProperties();

            /** @var Partner $partner */
            $partner = tenant();
            Storage::disk('s3')
                ->put("$partner->id/attachments/mails/" . $properties['name'], base64_decode($properties['contentBytes']));
            $path = Storage::disk('s3')->path("$partner->id/attachments/mails/" . $properties['name']);

            $attachments[] = [
                'encoding' => $properties['contentType'],
                'size' => $properties['size'],
                'name' => $properties['name'],
                'path' => $path,
                'file_url' => Storage::disk('s3')->url($path),
            ];
        });

        return $attachments;
    }

    /**
     * @throws Throwable
     * @throws GraphException
     * @throws GuzzleException
     */
    public function send(Request $request): array
    {
        $uuid = Str::uuid()->toString();
        $this->getGraphClient()->createRequest('POST', '/me/sendMail')
            ->attachBody($this->getMessage($request, $uuid)->getPayload())
            ->execute();

        $mail = $this->getMessageByUuid($uuid);
        $properties = $mail->getProperties();

        return [
            'email_id' => $mail->getId(),
            'thread_id' => $properties['conversationId'],
            'subject' => $mail->getSubject(),
            'message' => $mail->getBody()?->getContent(),
            'to' => collect($properties['toRecipients'])->map(fn($recipient) => $recipient['emailAddress'])->toArray()
        ];
    }

    /**
     * @param Request $request
     * @param string $uuid
     * @return Message
     * @throws FilesystemException
     */
    protected function getMessage(Request $request, string $uuid): Message
    {
        return (new Message)->setSubject($request->input('subject'))
            ->setContentType('HTML')
            ->setContent($request->input('content'))
            ->setUuid($uuid)
            ->setBcc($request->input('bcc'))
            ->setCc($request->input('cc'))
            ->setAttachments($request->input('attachments'))
            ->setRecipients($request->input('contact'));
    }

    /**
     * @throws GraphException
     * @throws GuzzleException
     * @throws Throwable
     */
    public function getMessageByUuid(string $uuid): ChatMessage
    {
        return $this->getGraphClient()
            ->createRequest('GET', "/me/messages?\$select=*&\$filter=singleValueExtendedProperties/Any(ep:ep/id eq 'String {" . $uuid . "}
             Name SendMailId' and ep/value eq '" . $uuid . "')&\$expand=singleValueExtendedProperties(\$filter=id eq 'String {" . $uuid . "} Name SendMailId')")
            ->setReturnType(ChatMessage::class)
            ->execute()[0];
    }

    /**
     * @throws GraphException
     * @throws Throwable
     * @throws GuzzleException
     */
    public function reply(Request $request, string $id): array
    {
        $uuid = Str::uuid()->toString();
        $this->getGraphClient()->createRequest('POST', "/me/messages/$id/reply")
            ->attachBody($this->getMessage($request, $uuid)->getPayload())->execute();

        $mail = $this->getMessageByUuid($uuid);
        $properties = $mail->getProperties();

        return [
            'email_id' => $mail->getId(),
            'thread_id' => $properties['conversationId'],
            'subject' => $mail->getSubject(),
            'message' => $mail->getBody()?->getContent(),
            'to' => collect($properties['toRecipients'])->map(fn($recipient) => $recipient['emailAddress'])->toArray()
        ];
    }

    /**
     * @param Request $request
     * @return Collection
     */
    public function getTenant(Request $request): Collection
    {
        $id = explode('/', $request->input('value.0.resource'))[1];

        return CentralMail::query()->where('email', $id)->get();
    }
}
