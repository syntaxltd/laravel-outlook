<?php

namespace Syntax\LaravelSocialIntegration\Modules\outlook;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Microsoft\Graph\Exception\GraphException;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model\ChatMessage;
use Syntax\LaravelSocialIntegration\Contracts\SocialClient;
use Syntax\LaravelSocialIntegration\Modules\outlook\messages\Mail;
use Throwable;

class LaravelOutlook implements SocialClient
{
    /**
     * @throws Throwable
     * @throws GraphException
     */
    public function all(): Collection
    {
        /** @var ChatMessage $message */
        $message = $this->getGraphClient()
            ->createRequest('GET', "/me/messages?\$select=*")
            ->setReturnType(ChatMessage::class)->execute();

        return collect($message);
    }

    /**
     * @throws Throwable
     */
    public function getGraphClient(): Graph
    {
        return (new Graph)->setAccessToken($this->auth()->getToken());
    }

    /**
     * Init auth class
     *
     * @return AuthClient
     */
    public function auth(): AuthClient
    {
        return new AuthClient;
    }

    /**
     * @throws Throwable
     * @throws GraphException
     * @throws GuzzleException
     */
    public function send(Request $request): array
    {
        $uuid = Str::uuid()->toString();

        $this->getGraphClient()->createRequest('POST', '/me/sendMail')
            ->attachBody($this->getMessage($request, $uuid)->getPayload())
            ->execute();

        $mail = $this->getMessageByUuid($uuid);

        return [
            'email_id' => $mail->getId(),
            'thread_id' => $mail->getProperties()['conversationId'],
            'subject' => $mail->getSubject(),
            'message' => $mail->getBody(),
        ];
    }

    /**
     * @param Request $request
     * @param string $uuid
     * @return Mail
     */
    protected function getMessage(Request $request, string $uuid): Mail
    {
        return (new Mail)->setSubject($request->input('subject'))
            ->setContentType('HTML')
            ->setContent($request->input('content'))
            ->setUuid($uuid)
            ->setRecipients(collect($request->input('contact'))->map(function ($contact) {
                return [
                    'name' => $contact['name'],
                    'address' => $contact['email'],
                ];
            })->toArray());
    }

    /**
     * @throws GraphException
     * @throws GuzzleException
     * @throws Throwable
     */
    public function getMessageByUuid(string $uuid): ChatMessage
    {
        return $this->getGraphClient()
            ->createRequest('GET', "/me/messages?\$select=*&\$filter=singleValueExtendedProperties/Any(ep:ep/id eq 'String {" . $uuid . "}
             Name SendMailId' and ep/value eq '" . $uuid . "')&\$expand=singleValueExtendedProperties(\$filter=id eq 'String {" . $uuid . "} Name SendMailId')")
            ->setReturnType(ChatMessage::class)
            ->execute()[0];
    }

    /**
     * @throws GraphException
     * @throws Throwable
     * @throws GuzzleException
     */
    public function reply(Request $request, string $id): array
    {
        $uuid = Str::uuid()->toString();

        $this->getGraphClient()->createRequest('POST', "/me/messages/$id/reply")
            ->attachBody($this->getMessage($request, $uuid)->getPayload())->execute();

        $mail = $this->getMessageByUuid($uuid);

        return [
            'email_id' => $mail->getId(),
            'thread_id' => $mail->getProperties()['conversationId'],
            'subject' => $mail->getSubject(),
            'message' => $mail->getBody(),
        ];
    }
}
