<?php


namespace Syntax\LaravelSocialIntegration\Modules\gmail\traits;


use Exception;
use Google_Service_Gmail_Message;
use Swift_Attachment;
use Swift_Message;
use Syntax\LaravelSocialIntegration\Modules\gmail\services\Mail;

trait Replyable
{

    use SendsParameters, HasHeaders;

    public function __construct()
    {
    }
    /**
     * @param  array  $parameters
     *
     * @return Replyable
     */
    public function optionalParameters(array $parameters): static
    {
        $this->parameters = $parameters;

        return $this;
    }
    private function setReplyThread()
    {
        $threadId = $this->getThreadId();
        if ($threadId) {
            $this->setHeader('In-Reply-To', $this->getHeader('From'));
            $this->setHeader('Message-ID', $this->getHeader('Message-Id'));
        }
    }

    public abstract function getId();
    public abstract function getThreadId();
    /**
     * Add a header to the email
     *
     * @param  string  $header
     * @param  string  $value
     */
    public function setHeader(string $header, string $value)
    {
        $swiftMessage = new Swift_Message();
        $headers = $swiftMessage->getHeaders();

        $headers->addTextHeader($header, $value);

    }

    private function setReplySubject()
    {
        if (!$this->subject) {
            $this->subject = $this->getSubject();
        }
    }

    private function setReplyTo()
    {
        if (!$this->to) {
            $replyTo = $this->getReplyTo();

            $this->to = $replyTo['email'];
            $this->nameTo = $replyTo['name'];
        }
    }

    /**
     * @throws Exception
     */
    private function setReplyFrom()
    {
        if (!$this->from) {
            $this->from = $this->getUser();
            if(!$this->from) {
                throw new Exception('Reply from is not defined');
            }
        }
    }

    public abstract function getSubject();

    public abstract function getReplyTo();

    public abstract function getUser();

    /**
     * @return Google_Service_Gmail_Message
     */
    private function getMessageBody(): Google_Service_Gmail_Message
    {
        $body = new Google_Service_Gmail_Message();

        $swiftMessage = new Swift_Message();
        $swiftMessage
            ->setSubject($this->subject)
            ->setFrom($this->from, $this->nameFrom)
            ->setTo($this->to, $this->nameTo)
            ->setCc($this->cc, $this->nameCc)
            ->setBcc($this->bcc, $this->nameBcc)
            ->setBody($this->message, 'text/html')
            ->setPriority('2');

            foreach ($this->attachments as $file) {
                $swiftMessage
                    ->attach(Swift_Attachment::fromPath($file));
            }

        $body->setRaw($this->base64_encode($swiftMessage->toString()));

        return $body;

    }

    private function base64_encode($data): string
    {
        return rtrim(strtr(base64_encode($data), ['+' => '-', '/' => '_']), '=');
    }


    /**
     * Reply to a specific email
     *
     * @return Mail
     * @throws Exception
     */
    public function reply(): Mail
    {
        if (!$this->getId()) {
            throw new Exception('This is a new email. Use send().');
        }

        $this->setReplyThread();
        $this->setReplySubject();
        $this->setReplyTo();
        $this->setReplyFrom();
        $body = $this->getMessageBody();
        $body->setThreadId($this->getThreadId());

        return new Mail($this->service->users_messages->send('me', $body, $this->parameters));
    }


    /**
     * Sends a new email
     *
     * @return self
     */
    public function send(): static
    {
        $body = $this->getMessageBody();

        $this->setMessage($this->service->users_messages->send('me', $body));

        return $this;
    }
}