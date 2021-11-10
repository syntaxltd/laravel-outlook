<?php


namespace Syntax\LaravelSocialIntegration\Modules\gmail\traits;


use Google_Service_Gmail_Message;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Swift_Message;

trait SendMail
{

    private $swiftMessage;

    /**
     * Gmail optional parameters
     *
     * @var array
     */
    private $parameters = [];

    /**
     * Text or html message to send
     *
     * @var string
     */
    private $message;

    /**
     * Subject of the email
     *
     * @var string
     */
    private $subject;

    /**
     * Sender's email
     *
     * @var string
     */
    private $from;

    /**
     * Sender's name
     *
     * @var  string
     */
    private $nameFrom;

    /**
     * Email of the recipient
     *
     * @var string|array
     */
    private $to;

    /**
     * Name of the recipient
     *
     * @var string
     */
    private $nameTo;

    /**
     * Single email or array of email for a carbon copy
     *
     * @var array|string
     */
    private $cc;

    /**
     * Name of the recipient
     *
     * @var string
     */
    private $nameCc;

    /**
     * Single email or array of email for a blind carbon copy
     *
     * @var array|string
     */
    private $bcc;

    /**
     * Name of the recipient
     *
     * @var string
     */
    private $nameBcc;

    /**
     * List of attachments
     *
     * @var array
     */
    private $attachments = [];

    public function __construct()
    {
        $this->swiftMessage = new Swift_Message();
    }


    /**
     * Receives the recipient's
     * If multiple recipients will receive the message an array should be used.
     * Example: array('receiver@domain.org', 'other@domain.org' => 'A name')
     *
     * If $name is passed and the first parameter is a string, this name will be
     * associated with the address.
     *
     * @param  string|array  $to
     *
     * @param  string|null  $name
     *
     * @return SendMail
     */
    public function to($to, $name = null): static
    {
        $this->to = $to;
        $this->nameTo = $name;

        return $this;
    }

    public function from($from, $name = null): static
    {
        $this->from = $from;
        $this->nameFrom = $name;

        return $this;
    }

    /**
     * @param  array|string  $cc
     *
     * @param  string|null  $name
     *
     * @return SendMail
     */
    public function cc($cc, $name = null): static
    {
        $this->cc = $this->emailList($cc, $name);
        $this->nameCc = $name;

        return $this;
    }

    private function emailList($list, $name = null)
    {
        if (is_array($list)) {
            return $this->convertEmailList($list, $name);
        } else {
            return $list;
        }
    }

    private function convertEmailList($emails, $name = null): array
    {
        $newList = [];
        $count = 0;
        foreach ($emails as $key => $email) {
            $emailName = isset($name[$count]) ? $name[$count] : explode('@', $email)[0];
            $newList[$email] = $emailName;
            $count = $count + 1;
        }

        return $newList;
    }

    /**
     * @param  array|string  $bcc
     *
     * @param  string|null  $name
     *
     * @return SendMail
     */
    public function bcc($bcc, $name = null): static
    {
        $this->bcc = $this->emailList($bcc, $name);
        $this->nameBcc = $name;

        return $this;
    }

    /**
     * @param  string  $subject
     *
     * @return SendMail
     */
    public function subject($subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * @param  string  $view
     * @param  array  $data
     * @param  array  $mergeData
     *
     * @return SendMail
     * @throws \Throwable
     */
    public function view($view, $data = [], $mergeData = []): static
    {
        $this->message = view($view, $data, $mergeData)->render();

        return $this;
    }

    /**
     * @param  string  $message
     *
     * @return SendMail
     */
    public function message($message): static
    {
        $this->message = $message;

        return $this;
    }

    /**
     * @return Google_Service_Gmail_Message
     */
    private function getMessageBody(): Google_Service_Gmail_Message
    {
        $body = new Google_Service_Gmail_Message();

        $this->swiftMessage
            ->setSubject($this->subject)
            ->setFrom($this->from, $this->nameFrom)
            ->setTo($this->to, $this->nameTo)
            ->setCc($this->cc, $this->nameCc)
            ->setBcc($this->bcc, $this->nameBcc)
            ->setBody($this->message, 'text/html')
            ->setPriority(2);

        $body->setRaw($this->base64_encode($this->swiftMessage->toString()));

        return $body;
    }
    private function base64_encode($data)
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

    protected abstract function setMessage(\Google_Service_Gmail_Message $message);

}