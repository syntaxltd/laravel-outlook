<?php


namespace Syntax\LaravelSocialIntegration\Modules\gmail\traits;


use Carbon\Carbon;
use Exception;
use Google_Service_Gmail_Message;
use Google_Service_Gmail_MessagePart;
use Illuminate\Support\Facades\Log;
use Swift_Message;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

trait SendMail
{
    use SendsParameters, HasParts;

    public Google_Service_Gmail_MessagePart|null $payload;

    public collection|null $parts;

    public function __construct()
    {
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
     * Return a UNIX version of the date
     *
     * @return int UNIX date
     */
    public function getInternalDate(): int
    {
        return $this->internalDate;
    }

    /**
     * Returns the labels of the email
     * Example: INBOX, STARRED, UNREAD
     *
     * @return array
     */
    public function getLabels(): array
    {
        return $this->labels;
    }

    /**
     * Returns approximate size of the email
     *
     * @return mixed
     */
    public function getSize(): mixed
    {
        return $this->size;
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
     * @return string
     */
    public function getSubject(): string
    {
        return $this->getHeader('Subject');
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
            'name'  => $name,
            'email' => $matches[1] ?? null,
        ];
    }

    /**
     * Returns email of sender
     *
     * @return string|null
     */
    public function getFromEmail(): ?string
    {
        $from = $this->getHeader('From');

        if (filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return $from;
        }

        preg_match('/<(.*)>/', $from, $matches);

        return $matches[1] ?? null;
    }

    /**
     * Returns name of the sender
     *
     * @return string|null
     */
    public function getFromName(): ?string
    {
        $from = $this->getHeader('From');

        return preg_replace('/ <(.*)>/', '', $from);
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
     * Returns array list of cc recipients
     *
     * @return array
     */
    public function getCc(): array
    {
        $allCc = $this->getHeader('Cc');

        return $this->formatEmailList($allCc);
    }

    /**
     * Returns array list of bcc recipients
     *
     * @return array
     */
    public function getBcc(): array
    {
        $allBcc = $this->getHeader('Bcc');

        return $this->formatEmailList($allBcc);
    }

    /**
     * Returns an array of emails from an string in RFC 822 format
     *
     * @param string $emails email list in RFC 822 format
     *
     * @return array
     */
    public function formatEmailList(string $emails): array
    {
        $all = [];
        $explodedEmails = explode(',', $emails);

        foreach ($explodedEmails as $email) {

            $item = [];

            preg_match('/<(.*)>/', $email, $matches);

            $item['email'] = str_replace(' ', '', $matches[1] ?? $email);

            $name = preg_replace('/ <(.*)>/', '', $email);

            if (Str::startsWith($name, ' ')) {
                $name = substr($name, 1);
            }

            $item['name'] = str_replace("\"", '', $name ?: null);

            $all[] = $item;

        }

        return $all;
    }

    /**
     * Returns the original date that the email was sent
     *
     * @return Carbon
     */
    public function getDate(): Carbon
    {
        return Carbon::parse($this->getHeader('Date'));
    }

    /**
     * Returns email of the original recipient
     *
     * @return string
     */
    public function getDeliveredTo(): string
    {
        return $this->getHeader('Delivered-To');
    }

    /**
     * Base64 version of the body
     *
     * @return string
     * @throws Exception
     */
    public function getRawPlainTextBody(): string
    {
        return $this->getPlainTextBody(true);
    }

    /**
     * @param bool $raw
     *
     * @return string
     * @throws Exception
     */
    public function getPlainTextBody(bool $raw = false): string
    {
        $content = $this->getBody();

        return $raw ? $content : $this->getDecodedBody($content);
    }

    /**
     * Returns a specific body part from an email
     *
     * @param string $type
     *
     * @return null|string
     * @throws Exception
     */
    public function getBody(string $type = 'text/plain'): ?string
    {
        $parts = $this->getAllParts($this->parts);

        try {
            if (!$parts->isEmpty()) {
                foreach ($parts as $part) {
                    if ($part->mimeType == $type) {
                        return $part->body->data;
                        //if there are no parts in payload, try to get data from body->data
                    } elseif ($this->payload->body->data) {
                        return $this->payload->body->data;
                    }
                }
            } else {
                return $this->payload->body->data;
            }
        } catch (Exception) {
            throw new Exception("Preload or load the single message before getting the body.");
        }

        return null;
    }

    /**
     * @return Google_Service_Gmail_Message
     */
    private function getMessageBody(): Google_Service_Gmail_Message
    {
        $body = new Google_Service_Gmail_Message();
        // Create the message
        $message = (new Swift_Message());
        $message
            ->setSubject($this->subject)
            ->setFrom($this->from, $this->nameFrom)
            ->setTo($this->to, $this->nameTo)
            ->setCc($this->cc, $this->nameCc)
            ->setBcc($this->bcc, $this->nameBcc)
            ->setBody($this->message, 'text/html')
            ->setPriority(2);

        $body->setRaw($this->base64_encode($message->toString()));

        return $body;
    }

    /**
     * Decodes the body from gmail to make it readable
     *
     * @param $content
     * @return bool|string
     */
    public function getDecodedBody($content): bool|string
    {
        $content = str_replace('_', '/', str_replace('-', '+', $content));

        return base64_decode($content);
    }

    private function base64_encode($data): string
    {
        return rtrim(strtr(base64_encode($data), ['+' => '-', '/' => '_']), '=');
    }

    /**
     * Sends a new email
     *
     * @return SendMail
     */
    public function sendMail(): static
    {
        $body = $this->getMessageBody();
        $this->setMessage($this->service->users_messages->send('me', $body, $this->parameters));

        return $this;
    }

    /**
     * Sets data from mail
     *
     * @param Google_Service_Gmail_Message $message
     */
    protected function setMessage(Google_Service_Gmail_Message $message)
    {
        $this->id = $message->getId();
        $this->internalDate = $message->getInternalDate();
        $this->labels = $message->getLabelIds();
        $this->size = $message->getSizeEstimate();
        $this->threadId = $message->getThreadId();
        $this->payload = $message->getPayload();
        if ($this->payload) {
            $this->parts = collect($this->payload->getParts());
        }
    }

    /**
     * Sets the metadata from Mail when preloaded
     */
    protected function setMetadata()
    {
        $this->to = $this->getTo();
        $from = $this->getFrom();
        $this->from = $from['email'] ?? null;
        $this->nameFrom = $from['email'] ?? null;

        $this->subject = $this->getSubject();
    }


}