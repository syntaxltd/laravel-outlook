<?php

namespace Dytechltd\LaravelOutlook\Models;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Microsoft\Graph\Exception\GraphException;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Http\GraphResponse;

class OutlookMail extends Model
{
    private $saveToSentItems = false;

    private $subject;

    private $body;

    private $to;

    /**
     * Save items to sent folder.
     *
     * @param bool $saveToSentItems
     * @return $this
     */
    public function saveToSentItems(bool $saveToSentItems): self
    {
        $this->saveToSentItems = $saveToSentItems;

        return $this;
    }

    /**
     * Mail subject.
     *
     * @param string $subject
     * @return $this
     */
    public function withSubject(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * Mail body.
     *
     * @param string $body
     * @return $this
     */
    public function withBody(string $body): self
    {
        $this->body = $body;

        return $this;
    }

    /**
     * Recipients.
     *
     * @param array $to
     * @return $this
     */
    public function to(array $to): self
    {
        $this->to = $to;

        return $this;
    }

    /**
     * @throws GraphException
     * @throws GuzzleException
     */
    public function sendMail()
    {
        /**
         * @var Graph $graph
         */
        $graph = app('laravel-outlook')->getGraphClient();

        /**
         * @var GraphResponse $response
         */
        $graph->createRequest('POST', '/me/sendMail')->attachBody([
            'saveToSentItems' => $this->saveToSentItems,
            'message' => [
                "subject" => $this->subject,
                "body" => [
                    "contentType" => "Text",
                    "content" => $this->body,
                ],
                "toRecipients" => [
                    $this->to
                ],
                "attachments" => [
                    [
                        "@odata.type" => "#microsoft.graph.fileAttachment",
                        "name" => "arrow-left.png",
                        "contentType" => "image/png",
                        "contentBytes" => base64_encode(file_get_contents(public_path('arrow-left.png'))),
                    ]
                ]
            ],
        ])->execute();
    }

    /**
     * @throws GuzzleException
     * @throws GraphException
     */
    public function get(): Collection
    {
        /**
         * @var Graph $graph
         */
        $graph = app('laravel-outlook')->getGraphClient();

        /**
         * @var GraphResponse $response
         */
        $response = $graph->createRequest('GET', '/me/messages')
            ->addHeaders([
                'outlook.body-content-type' => 'text'
            ])->execute();

        return collect($response->getBody()['value']);
    }

    /**
     * @throws GuzzleException
     * @throws GraphException
     */
    public function getMessage(string $id): Collection
    {
        /**
         * @var Graph $graph
         */
        $graph = app('laravel-outlook')->getGraphClient();

        /**
         * @var GraphResponse $response
         */
        $response = $graph->createRequest('GET', "/me/messages/$id")
            ->addHeaders([
                'outlook.body-content-type' => 'text'
            ])->execute();

        return collect($response->getBody());
    }
}