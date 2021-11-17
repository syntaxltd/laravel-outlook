<?php


namespace Syntax\LaravelSocialIntegration\Modules\gmail\traits;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;

trait SendsParameters
{
    public array $parameters = [];

    public string $id;

    public string $threadId;

    public string|null $historyId;

    public string $message;

    public string|null $subject;

    public string $from;

    public string|null $nameFrom;

    public string|array $to;

    public string|null $nameTo;

    public array|string|null $cc;

    public string|null $nameCc;

    public array|string|null $bcc;

    public string|null $nameBcc;

    public array $attachments = [];

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
     * @return SendsParameters
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
     * @return SendsParameters
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
     * Returns an array of emails from an string in RFC 822 format
     *
     * @param string|null $emails email list in RFC 822 format
     *
     * @return array
     */
    public function formatEmailList(string|null $emails): array
    {
        $all = [];
        $explodedEmails = explode(',', $emails);

        foreach ($explodedEmails as $email) {

            $item = [];

            preg_match('/<(.*)>/', $email, $matches);

            $item['email'] = str_replace(' ', '', $matches[1] ?? $email);

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
     * @param  array|string|null  $bcc
     *
     * @param  string|null  $name
     *
     * @return SendsParameters
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
     * @return SendsParameters
     */
    public function subject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * @param  string  $message
     *
     * @return SendsParameters
     */
    public function message(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    /**
     * Attaches new file to the email from the Storage folder
     *
     * @param  array  $files  comma separated of files
     *
     * @return SendsParameters
     * @throws Exception
     */
    public function attach(...$files): static
    {
        $this->attachments = $files;
        return $this;
    }
}