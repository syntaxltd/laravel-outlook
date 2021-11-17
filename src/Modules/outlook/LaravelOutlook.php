<?php

namespace Syntax\LaravelSocialIntegration\Modules\outlook;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
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
    public function all(): mixed
    {
        /** @var ChatMessage $message */
        $message = $this->getGraphClient()->createRequest('GET', '/groups')->execute();

//        info('Replies', [$message, $message->getReplies()]);
//        info('Replies', [$message, $message->getReplies()]);

        return $message;
    }

    /**
     * @throws Throwable
     */
    public function getGraphClient(): Graph
    {
        return (new Graph)->setAccessToken($this->auth()->getToken());
    }

    public function auth(): AuthClient
    {
        return new AuthClient;
    }

    /**
     * @throws GraphException
     * @throws Throwable
     */
    public function send(Request $request): array
    {
        $mail = $this->createMessage($request);

        $this->getGraphClient()->createRequest('POST', "/me/messages/{$mail->getId()}/send")
            ->execute();

        info('Mail properties', $mail->getProperties());

        return [
            'email_id' => $mail->getId(),
            'thread_id' => $mail->getChatId(),
            'subject' => $mail->getSubject(),
            'message' => $mail->getBody(),
        ];
    }

    /**
     * @throws Throwable
     * @throws GraphException
     * @throws GuzzleException
     */
    public function createMessage(Request $request): ChatMessage
    {
        $message = (new Mail)->setSubject($request->input('subject'))
            ->setContentType('HTML')
            ->setContent($request->input('content'))
            ->setRecipients(collect($request->input('contact'))->map(function ($contact) {
                return [
                    'name' => $contact['name'],
                    'address' => $contact['email'],
                ];
            })->toArray());

        return $this->getGraphClient()->createRequest('POST', '/me/messages')
            ->attachBody($message->getPayload()['message'])
            ->setReturnType(ChatMessage::class)->execute();
    }

    /**
     * @throws GraphException
     * @throws Throwable
     * @throws GuzzleException
     */
    public function reply(Request $request, string $id): array
    {
        $message = (new Mail)->setSubject($request->input('subject'))
            ->setContentType('HTML')
            ->setContent($request->input('content'))
            ->setRecipients(collect($request->input('contact'))->map(function ($contact) {
                return [
                    'name' => $contact['name'],
                    'address' => $contact['email'],
                ];
            })->toArray());

        info('Mail', [$id]);
        $mail = $this->createReply($id);

//        return $this->getGraphClient()->createRequest('POST', "/me/messages/$id/createReply")
//            ->attachBody($message->getPayload()['message'])->execute();

        return [];
    }

    /**
     * @throws Throwable
     * @throws GraphException
     * @throws GuzzleException
     */
    public function createReply(string $id): ChatMessage
    {
        return $this->getGraphClient()->createRequest('POST', "/me/messages/AQMkADAwATM0MDAAMS0wMzRmLTFlODYtMDACLTAwCgBGAAADYM_VhSlnBE6ETfWldtGBFQcAco1g90z3n0_VnY70dzdOagAAAgEPAAAAco1g90z3n0_VnY70dzdOagAC2487BgAAAA==/createReply")
            ->setReturnType(ChatMessage::class)->execute();
    }
}
