<?php


namespace Syntax\LaravelSocialIntegration\Modules\gmail\traits;


use Google_Service_Gmail_Message;
use Swift_Attachment;
use Swift_Message;
use Syntax\LaravelSocialIntegration\Modules\gmail\services\Mail;

trait Replyable
{

    use SendsParameters, HasHeaders;

    private $swiftMessage;

    public function __construct()
    {
        $this->swiftMessage = new Swift_Message();
    }
    /**
     * @param  array  $parameters
     *
     * @return Replyable
     */
    public function optionalParameters(array $parameters)
    {
        $this->parameters = $parameters;

        return $this;
    }

    /**
     * Reply to a specific email
     *
     * @return Mail
     * @throws \Exception
     */
    public function reply()
    {
        if (!$this->getId()) {
            throw new \Exception('This is a new email. Use send().');
        }

        $this->setReplyThread();
        $this->setReplySubject();
        $this->setReplyTo();
        $this->setReplyFrom();
        $body = $this->getMessageBody();
        $body->setThreadId($this->getThreadId());

        return new Mail($this->service->users_messages->send('me', $body, $this->parameters));
    }

    private function setReplyThread()
    {
        $threadId = $this->getThreadId();
        if ($threadId) {
            $this->setHeader('In-Reply-To', $this->getHeader('In-Reply-To'));
            $this->setHeader('References', $this->getHeader('References'));
            $this->setHeader('Message-ID', $this->getHeader('Message-ID'));
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
    public function setHeader($header, $value)
    {
        $headers = $this->swiftMessage->getHeaders();

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

    private function setReplyFrom()
    {
        if (!$this->from) {
            $this->from = $this->getUser();
            if(!$this->from) {
                throw new \Exception('Reply from is not defined');
            }
        }
    }

    public abstract function getSubject();

    public abstract function getReplyTo();

    public abstract function getUser();

    /**
     * @return Google_Service_Gmail_Message
     */
    private function getMessageBody()
    {
        $body = new Google_Service_Gmail_Message();

        $this->swiftMessage
            ->setSubject($this->subject)
            ->setFrom($this->from, $this->nameFrom)
            ->setTo($this->to, $this->nameTo)
            ->setCc($this->cc, $this->nameCc)
            ->setBcc($this->bcc, $this->nameBcc)
            ->setBody($this->message, 'text/html')
            ->setPriority('2');

        foreach ($this->attachments as $file) {
            $this->swiftMessage
                ->attach(Swift_Attachment::fromPath($file));
        }

        $body->setRaw($this->base64_encode($this->swiftMessage->toString()));

        return $body;

    }

    private function base64_encode($data)
    {
        return rtrim(strtr(base64_encode($data), ['+' => '-', '/' => '_']), '=');
    }
}