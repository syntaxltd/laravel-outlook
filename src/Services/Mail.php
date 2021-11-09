<?php


namespace Dytechltd\LaravelSocialIntegration\Services;

use Dytechltd\LaravelSocialIntegration\LaravelGmail;
use Google_Service_Gmail;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Mail extends LaravelGmail
{

    /**
     * @var
     */
    public $id;

    /**
     * @var
     */
    public $userId;

    /**
     * @var
     */
    public $internalDate;

    /**
     * @var
     */
    public $labels;

    /**
     * @var
     */
    public $size;

    /**
     * @var
     */
    public $threadId;

    /**
     * @var
     */
    public $to;

    /**
     * @var
     */
    public $from;
    /**
     * @var
     */
    public $nameFrom;

    /**
     * @var
     */
    public $subject;

    /**
     * @var
     */
    public $body;

    /**
     * @var \Google_Service_Gmail_MessagePart
     */
    public $payload;

    public $parts;


    /**
     * @var Google_Service_Gmail
     */
    public $service;

    /**
     * SingleMessage constructor.
     *
     * @param \Google_Service_Gmail_Message $message
     * @param bool $preload
     * @param int $userId
     */
    public function __construct(\Google_Service_Gmail_Message $message = null, $preload = false, $userId = null)
    {
        $this->service = new Google_Service_Gmail($this);

        parent::__construct(config(), $userId);

        if (!is_null($message)) {
            if ($preload) {
                $message = $this->service->users_messages->get('me', $message->getId());
            }

            $this->setUserId($userId);

            $this->setMessage($message);
            $this->setMetadata();
        }
    }

    /**
     * Sets data from mail
     *
     * @param \Google_Service_Gmail_Message $message
     */
    protected function setMessage(\Google_Service_Gmail_Message $message): void
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
     * @throws \Exception
     */
    protected function setMetadata(): void
    {
        $this->to = $this->getTo();
        $from = $this->getFrom();
        $this->from = isset($from['email']) ? $from['email'] : null;
        $this->nameFrom = isset($from['email']) ? $from['email'] : null;

        $this->subject = $this->getSubject();
        $this->body = $this->getBody();
    }

    /**
     * Returns array list of recipients
     *
     * @return array
     */
    public function getTo()
    {
        $allTo = $this->getHeader('To');

        return $this->formatEmailList($allTo);
    }

    /**
     * Gets a single header from an existing email by name.
     *
     * @param $headerName
     *
     * @param string $regex if this is set, value will be evaluated with the give regular expression.
     *
     * @return null|string
     */
    public function getHeader($headerName, $regex = null)
    {
        $headers = $this->getHeaders();

        $value = null;

        foreach ($headers as $header) {
            if ($header->key === $headerName) {
                $value = $header->value;
                if (!is_null($regex)) {
                    preg_match_all($regex, $header->value, $value);
                }
                break;
            }
        }

        if (is_array($value)) {
            return isset($value[1]) ? $value[1] : null;
        }

        return $value;
    }

    /**
     * Returns all the headers of the email
     *
     * @return Collection
     */
    public function getHeaders()
    {
        return $this->buildHeaders($this->payload->getHeaders());
    }

    /**
     * Gets all the headers from an email and returns a collections
     *
     * @param $emailHeaders
     * @return Collection
     */
    private function buildHeaders($emailHeaders): Collection
    {
        $headers = [];

        foreach ($emailHeaders as $header) {
            /** @var \Google_Service_Gmail_MessagePartHeader $header */

            $head = new \stdClass();

            $head->key = $header->getName();
            $head->value = $header->getValue();

            $headers[] = $head;
        }

        return collect($headers);
    }

    /**
     * Returns an array of emails from an string in RFC 822 format
     *
     * @param string $emails email list in RFC 822 format
     *
     * @return array
     */
    public function formatEmailList($emails)
    {
        $all = [];
        $explodedEmails = explode(',', $emails);

        foreach ($explodedEmails as $email) {

            $item = [];

            preg_match('/<(.*)>/', $email, $matches);

            $item['email'] = str_replace(' ', '', isset($matches[1]) ? $matches[1] : $email);

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
     * Returns array of name and email of each recipient
     *
     * @param string|null $email
     * @return array
     */
    public function getFrom($email = null)
    {
        $from = $email ? $email : $this->getHeader('From');

        preg_match('/<(.*)>/', $from, $matches);

        $name = preg_replace('/ <(.*)>/', '', $from);

        return [
            'name' => $name,
            'email' => isset($matches[1]) ? $matches[1] : null,
        ];
    }

    /**
     * Returns the subject of the email
     *
     * @return string
     */
    public function getSubject()
    {
        return $this->getHeader('Subject');
    }

    /**
     * Returns a specific body part from an email
     *
     * @param string $type
     *
     * @return null|string
     * @throws \Exception
     */
    public function getBody($type = 'text/plain')
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
        } catch (\Exception $exception) {
            throw new \Exception("Preload or load the single message before getting the body.");
        }

        return null;
    }

    /**
     * Returns ID of the email
     *
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }
}