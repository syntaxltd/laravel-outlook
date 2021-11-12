<?php


namespace Syntax\LaravelSocialIntegration\Modules\gmail\traits;

trait SendsParameters
{
    private array $parameters = [];

    public int|string $id;

    public int|string $threadId;

    private string $message;

    private string $subject;

    private string|null $from;

    private string|null $nameFrom;

    private string|array $to;

    private string|null $nameTo;

    private array|string|null $cc;

    private string|null $nameCc;

    private array|string|null $bcc;

    private string|null $nameBcc;

    private array $attachments = [];

    public int|null $internalDate;

    public array $labels;

    public mixed $size;

    public function __construct()
    {
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
    public function to(array|string $to, string|null $name = null): static
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
     * @param  array|string|null  $cc
     *
     * @param  string|null  $name
     *
     * @return SendMail
     */
    public function cc(array|string|null $cc, string|null $name = null): static
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
        foreach ($emails as $email) {
            $emailName = $name[$count] ?? explode('@', $email)[0];
            $newList[$email] = $emailName;
            $count = $count + 1;
        }

        return $newList;
    }

    /**
     * @param  array|string|null  $bcc
     *
     * @param  string|null  $name
     *
     * @return SendMail
     */
    public function bcc(array|string|null $bcc, string|null $name = null): static
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
    public function subject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * @param  string  $message
     *
     * @return SendMail
     */
    public function message(string $message): static
    {
        $this->message = $message;

        return $this;
    }

}