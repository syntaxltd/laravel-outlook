<?php

namespace Syntax\LaravelMailIntegration\Modules\gmail\services;

use App\Models\Partner;
use Exception;
use Google_Service_Gmail;
use Google_Service_Gmail_Message;
use Google_Service_Gmail_MessagePart;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Safe\Exceptions\UrlException;
use Syntax\LaravelMailIntegration\Models\MailAccessToken;
use Syntax\LaravelMailIntegration\Modules\gmail\traits\Replyable;
use function Safe\base64_decode;

class GmailMessages extends GmailConnection
{
    use Replyable;

    public Google_Service_Gmail_MessagePart $payload;

    public array $parts = [];


    public function __construct(Google_Service_Gmail_Message $message = null)
    {
        $this->service = new Google_Service_Gmail($this);

        parent::__construct();

        if (!is_null($message)) {
            $this->setMessage($message);
            $this->setMetadata();
        }
    }

    /**
     * Sets data from mail
     *
     * @param Google_Service_Gmail_Message $message
     */
    protected function setMessage(Google_Service_Gmail_Message $message): void
    {
        $this->id = $message->getId();
        $this->internalDate = $message->getInternalDate();
        $this->labels = $message->getLabelIds();
        $this->size = $message->getSizeEstimate();
        $this->threadId = $message->getThreadId();
        $this->historyId = $message->getHistoryId();
        $this->payload = $message->getPayload() ?: $this->get($this->id)->getPayload();
        if ($this->payload->getParts()) {
            $parts = collect($this->payload->getParts());
            foreach ($parts as $part){
                /** @var Google_Service_Gmail_MessagePart $part */
                $body = collect($part->getBody());
                $body->prepend($part->getFilename(), 'fileName');
                $body->prepend($part->getMimeType(), 'mimeType');
                array_push($this->parts, $body);
            }
        }else{
            array_push($this->parts, collect($this->payload->getBody()));
        }
    }

    /**
     * Sets the metadata from Mail when preloaded
     */
    protected function setMetadata(): void
    {
        $this->to = $this->getTo();
        $from = $this->getFrom();
        $this->from = $from['email'] ?: $from['name'];
        $this->nameFrom = $from['name'];

        $this->subject = $this->getSubject();
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
     * Returns array list of recipients
     *
     * @return array
     */
    public function getTo(): array
    {
        $allTo = $this->getHeader('To');

        return $this->formatEmailList($allTo);
    }

    /**
     * Returns array of name and email of each recipient
     *
     * @param string|null $email
     * @return array
     */
    public function getFrom(string|null $email = null): array
    {
        $from = $email ?: $this->getHeader('From');

        preg_match('/<(.*)>/', $from, $matches);

        $name = preg_replace('/ <(.*)>/', '', $from);

        return [
            'name' => $name,
            'email' => $matches[1] ?? null,
        ];
    }

    /**
     * Returns the subject of the email
     *
     * @return string|null
     */
    public function getSubject(): ?string
    {
        return $this->getHeader('Subject');
    }

    /**
     * Returns ID of the email
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Returns all the headers of the email
     *
     * @return Collection
     */
    public function getHeaders(): Collection
    {
        return $this->buildHeaders($this->payload->getHeaders());
    }

    /**
     * Returns the subject of the email
     *
     * @return array|string
     */
    public function getReplyTo(): array|string
    {
        $replyTo = $this->getHeader('Reply-To');

        return $this->getFrom($replyTo ?: $this->getHeader('From'));
    }

    /**
     * Returns thread ID of the email
     *
     * @return string
     */
    public function getThreadId(): string
    {
        return $this->threadId;
    }

    /**
     * Returns thread ID of the email
     *
     * @return string|null
     */
    public function getHistoryId(): ?string
    {
        return $this->historyId;
    }

    /**
     * Gets the user email from the config file
     *
     * @return string
     */
    public function getUser(): string
    {
        /** @var MailAccessToken $user */
        $user = MailAccessToken::Where('partner_user_id', Auth::id())->where('type', 'gmail')->first();
        return $user->email;
    }

    /**
     * Decodes the body from gmail to make it readable
     *
     * @param string $content
     * @return string
     * @throws UrlException
     */
    public function getDecodedBody(string $content): string
    {
        $content = str_replace('_', '/', str_replace('-', '+', $content));

        return base64_decode($content);
    }
    /**
     * Returns a specific body part from an email
     *
     * @param string $type
     *
     * @return null|string
     * @throws Exception
     */
    private function getBody(string $type = 'text/plain'): ?string
    {
        foreach ($this->parts as $part) {
            $body = $part->filter(function ($value, $key) {
                return $key === 'data';
            })->map(function ($item) {
                return $item;
            })->toArray();
            return$body['data'];
        };

        return null;
    }

    /**
     * Gets the HTML body
     *
     *
     * @return string|null
     * @throws Exception
     */
    public function getHtmlBody(): string|null
    {
        $content = $this->getBody();
        return $content ? $this->getDecodedBody($content) : null;
    }
    /**
     * Returns a collection of attachment
     *
     * @return array
     * @throws Exception
     */
    public function getAttachments(): array
    {
        $attachments = [];
        foreach ($this->parts as $part) {
            $content = $part->map(function ($item ) {
                return $item;
            })->toArray();
            if ($content['attachmentId'] && !is_null($content['attachmentId'])) {
                $attachment = $this->service->users_messages_attachments->get('me', $this->id, $content['attachmentId']);

                /** @var Partner $partner */
                $partner = tenant();
                Storage::disk('s3')->put("$partner->id/attachments/mails/" . $content['fileName'], $this->getDecodedBody($attachment->getData()));
                $path = Storage::disk('s3')->path("$partner->id/attachments/mails/" . $content['fileName']);

                $attachments[] = [
                    'encoding' => $content['mimeType'],
                    'size' => $content['size'],
                    'name' => $content['fileName'],
                    'path' => $path,
                    'file_url' => Storage::disk('s3')->url($path),
                ];
            }
        };
        return $attachments;
    }
}
