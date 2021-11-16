<?php
namespace Syntax\LaravelSocialIntegration\Modules\gmail\services;

use App\Models\PartnerUser;
use Google_Service_Gmail;
use Google_Service_Gmail_Message;
use Google_Service_Gmail_MessagePart;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
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
     * Returns ID of the email
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
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
            'name'  => $name,
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
    public function getThreadId()
    {
        return $this->threadId;
    }

    /**
     * Gets the user email from the config file
     *
     * @return mixed
     */
    public function getUser(): mixed
    {
        return PartnerUser::find(Auth::id())->email;
    }

    /**
     * Sends a new email
     *
     * @return self
     */
    public function send(): static
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
    protected function setMessage(Google_Service_Gmail_Message $message):void
    {
        $this->id = $message->getId();
        $this->internalDate = $message->getInternalDate();
        $this->labels = $message->getLabelIds();
        $this->size = $message->getSizeEstimate();
        $this->threadId = $message->getThreadId();
        if ($message->getPayload()) {
            $this->payload = $message->getPayload();
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
        $this->from = $from['email'];
        $this->nameFrom = $from['email'];

        $this->subject = $this->getSubject();
    }

}