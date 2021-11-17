<?php

namespace Syntax\LaravelSocialIntegration\Modules\gmail\services;

use Google_Service_Gmail;
use Google_Service_Gmail_Message;
use Google_Service_Gmail_MessagePart;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Syntax\LaravelSocialIntegration\Models\SocialAccessToken;
use Syntax\LaravelSocialIntegration\Modules\gmail\traits\HasHeaders;
use Syntax\LaravelSocialIntegration\Modules\gmail\traits\Replyable;
use Syntax\LaravelSocialIntegration\Modules\gmail\traits\SendsParameters;

class Mail extends GmailConnection
{
    use SendsParameters, HasHeaders, Replyable;

    public Google_Service_Gmail_MessagePart $payload;

    public collection $parts;


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
        $this->payload = $this->get($message->getId())->getPayload();
        if ($this->payload) {
            $this->parts = collect($this->payload->getParts());
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
        /** @var SocialAccessToken $user */
        $user = SocialAccessToken::Where('partner_user_id', Auth::id())->where('type', 'gmail')->first();
        return $user->email;
    }


}
