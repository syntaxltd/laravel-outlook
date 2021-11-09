<?php

namespace Dytechltd\LaravelOutlook\Services;

use Dytechltd\LaravelOutlook\LaravelGmail;
use Dytechltd\LaravelOutlook\Traits\Filterable;
use Dytechltd\LaravelOutlook\Traits\SendsParameters;
use Google_Service_Gmail;

class Message
{
    use Filterable, SendsParameters;

	public $service;

	public $preload = false;

	public $pageToken;

	public $client;

	/**
	 * Optional parameter for getting single and multiple emails
	 *
	 * @var array
	 */
	protected $params = [];

	/**
	 * Message constructor.
	 *
	 * @param  LaravelGmail  $client
	 */
	public function __construct(LaravelGmail $client)
	{
		$this->client = $client;
		$this->service = new Google_Service_Gmail($client);
	}

	/**
	 * Returns a collection of Mail instances
	 *
	 * @param  string|null  $pageToken
	 *
	 * @return array
     * @throws \Google_Exception
	 */
	public function all(string $pageToken = null)
	{
        $mails = [];
		$response = $this->getMessagesResponse();

		$messages = $response->getMessages();

        $mails = count($messages) > 0 ? $this->batchRequest($messages) : [];

        return $mails;
	}
    /**
     * Preload the information on each Mail objects.
     * If is not preload you will have to call the load method from the Mail class
     * @return $this
     * @see Mail::load()
     *
     */
    public function preload()
    {
        $this->preload = true;

        return $this;
    }

	/**
	 * @return \Google_Service_Gmail_ListMessagesResponse|object
	 * @throws \Google_Exception
	 */
	private function getMessagesResponse()
	{

		$responseOrRequest = $this->service->users_messages->listUsersMessages('me', $this->params);
		//dd($responseOrRequest->getMessages());

		if (get_class($responseOrRequest) === "GuzzleHttp\Psr7\Request") {
			$response = $this->service->getClient()->execute($responseOrRequest,
				'Google_Service_Gmail_ListMessagesResponse');

			return $response;
		}

		return $responseOrRequest;
	}

    /**
     * @param $id
     *
     * @return \Google_Service_Gmail_Message
     */
    private function getRequest($id)
    {
        return $this->service->users_messages->get('me', $id);
    }

    /**
     * Creates a batch request to get all emails in a single call
     *
     * @param $allMessages
     *
     * @return array|null
     */
    public function batchRequest($allMessages)
    {
        $this->client->setUseBatch(true);

        $batch = $this->service->createBatch();

        foreach ($allMessages as $key => $message) {
            $batch->add($this->getRequest($message->getId()), $key);
        }

        $messagesBatch = $batch->execute();

        $this->client->setUseBatch(false);

        $messages = [];

        foreach ($messagesBatch as $message) {
            $messages[] = new Mail($message, false, $this->client->userId);
        }

        return $messages;
    }

}
