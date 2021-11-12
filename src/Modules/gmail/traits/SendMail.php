<?php


namespace Syntax\LaravelSocialIntegration\Modules\gmail\traits;


use Google_Service_Gmail_Message;
use Google_Service_Gmail_MessagePart;
use Swift_Message;
use Illuminate\Support\Collection;

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