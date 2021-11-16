<?php

namespace Syntax\LaravelSocialIntegration\Modules\outlook\messages;

class Mail
{
    private string $subject;

    private array $content;

    private array $recipients;

    private array $attachments = [];

    private array $cc = [];

    private array $bcc = [];

    private bool $saveToSentItems = true;

    /**
     * @param string $subject
     * @return Mail
     */
    public function setSubject(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * @param string $content
     * @return Mail
     */
    public function setContent(string $content): self
    {
        $this->content = [
            'contentType' => 'Text',
            'content' => $content,
        ];

        return $this;
    }

    /**
     * @param array $recipients
     * @return Mail
     */
    public function setRecipients(array $recipients): self
    {
        $this->recipients = collect($recipients)->map(function ($recipient) {
            return [
                'emailAddress' => $recipient
            ];
        })->toArray();

        return $this;
    }

    /**
     * @param array $attachments
     * @return Mail
     */
    public function setAttachments(array $attachments): self
    {
        $this->attachments = $attachments;

        return $this;
    }

    /**
     * @param array $cc
     * @return Mail
     */
    public function setCc(array $cc): self
    {
        $this->cc = collect($$cc)->map(function ($recipient) {
            return [
                'emailAddress' => $recipient
            ];
        })->toArray();

        return $this;
    }

    /**
     * @param array $bcc
     * @return Mail
     */
    public function setBcc(array $bcc): self
    {
        $this->bcc = collect($bcc)->map(function ($item) {
            return [
                'emailAddress' => $item,
            ];
        })->toArray();

        return $this;
    }

    /**
     * @param bool $saveToSentItems
     * @return Mail
     */
    public function setSaveToSentItems(bool $saveToSentItems): self
    {
        $this->saveToSentItems = $saveToSentItems;

        return $this;
    }

    public function getPayload(): array
    {
        return [
            'saveToSentItems' => $this->saveToSentItems,
            'message' => [
                'subject' => $this->subject,
                'body' => $this->content,
                'toRecipients' => $this->recipients,
                'ccRecipients' => $this->cc,
                'bccRecipients' => $this->bcc,
                'attachments' => $this->attachments,
            ],
        ];
    }
}