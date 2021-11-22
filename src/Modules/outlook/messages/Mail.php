<?php

namespace Syntax\LaravelMailIntegration\Modules\outlook\messages;

use Safe\Exceptions\FilesystemException;
use function Safe\file_get_contents;

class Mail
{
    private string $subject;

    private array $content;

    private array $recipients;

    private array $attachments = [];

    private array $cc = [];

    private array $bcc = [];

    private bool $saveToSentItems = true;

    private string $contentType = 'Text';

    private string $uuid;

    private string $comment;

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
            'contentType' => $this->contentType,
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
                'emailAddress' => [
                    'name' => $recipient['name'],
                    'address' => $recipient['email'],
                ]
            ];
        })->toArray();

        return $this;
    }

    /**
     * @param array|null $attachments
     * @return Mail
     * @throws FilesystemException
     */
    public function setAttachments(?array $attachments): self
    {
        collect($attachments ?? [])->each(function ($attachment) {
            $this->attachments[] = [
                "@odata.type" => "#microsoft.graph.fileAttachment",
                "name" => $attachment['name'],
                "contentType" => $attachment['encoding'],
                "contentBytes" => base64_encode(file_get_contents($attachment['file_url'])),
            ];
        });

        return $this;
    }

    /**
     * @param array|null $cc
     * @return Mail
     */
    public function setCc(?array $cc): self
    {
        $this->cc = collect($cc ?? [])->map(function ($recipient) {
            return [
                'emailAddress' => [
                    'name' => $recipient['name'],
                    'address' => $recipient['email'],
                ]
            ];
        })->toArray();

        return $this;
    }

    /**
     * @param array|null $bcc
     * @return Mail
     */
    public function setBcc(?array $bcc): self
    {
        $this->bcc = collect($bcc ?? [])->map(function ($item) {
            return [
                'emailAddress' => [
                    'name' => $item['name'],
                    'address' => $item['email'],
                ],
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

    /**
     * @param string $contentType
     * @return Mail
     */
    public function setContentType(string $contentType): self
    {
        $this->contentType = $contentType;

        return $this;
    }

    /**
     * @param string $uuid
     * @return Mail
     */
    public function setUuid(string $uuid): self
    {
        $this->uuid = $uuid;

        return $this;
    }

    /**
     * @param string $comment
     * @return Mail
     */
    public function setComment(string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getPayload(): array
    {
        $message = [
            'saveToSentItems' => $this->saveToSentItems,
            'message' => [
                'subject' => $this->subject,
                'body' => $this->content,
                'toRecipients' => $this->recipients,
                'ccRecipients' => $this->cc,
                'bccRecipients' => $this->bcc,
                'attachments' => $this->attachments,
                'singleValueExtendedProperties' => [
                    [
                        'id' => 'String {' . $this->uuid . '} Name SendMailId',
                        'value' => $this->uuid,
                    ]
                ]
            ],
        ];

        if (isset($this->comment)) {
            $message['comment'] = $this->comment;
        }

        return $message;
    }
}
