<?php

namespace Syntax\LaravelSocialIntegration\Modules\outlook;

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
        return $this->getGraphClient()->createRequest('GET', '/me/messages?$select=*')
            ->setReturnType(ChatMessage::class)->execute();
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
    public function send(Request $request): void
    {
        $message = (new Mail)->setSubject($request->input('subject'))
            ->setContent($request->input('content'))
            ->setRecipients($request->input('recipients'));

        $this->getGraphClient()->createRequest('POST', '/me/sendMail')
            ->attachBody($message->getPayload())->execute();
    }
}
