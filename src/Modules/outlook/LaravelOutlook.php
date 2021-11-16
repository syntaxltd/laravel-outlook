<?php

namespace Syntax\LaravelSocialIntegration\Modules\outlook;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Microsoft\Graph\Exception\GraphException;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Model\ChatMessage;
use Safe\Exceptions\JsonException;
use Syntax\LaravelSocialIntegration\Contracts\SocialClient;
use Syntax\LaravelSocialIntegration\Models\SocialAccessMail;
use Syntax\LaravelSocialIntegration\Models\SocialAccessToken;
use Syntax\LaravelSocialIntegration\Modules\outlook\messages\Mail;
use Throwable;
use function Safe\json_encode;

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
    public function send(Request $request): ChatMessage
    {
        $mail = $this->createMessage($request);
        $this->storeMail($mail, $request);

        $this->getGraphClient()->createRequest('POST', "/me/messages/{$mail->getId()}/send")
            ->execute();

        return $mail;
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
     * @throws JsonException
     */
    private function storeMail(ChatMessage $mail, Request $request): void
    {
        /** @var SocialAccessToken $token */
        $token = SocialAccessToken::query()->where([
            'partner_user_id' => auth('partneruser')->id(),
            'type' => 'outlook',
        ])->first();

        $email = new SocialAccessMail;
        $email->parentable_id = $request->input('parent.id');
        $email->parentable_type = 'App\Models\\' . Str::ucfirst($request->input('parent.type'));
        $email->email_id = $mail->getId();
        $email->thread_id = $mail->getChatId();
        $email->token_id = $token->id;
        $email->data = json_encode([
            'to' => $request->input('contact'),
            'from' => $token->email,
            'subject' => $mail->getSubject(),
            'message' => $mail->getBody(),
        ]);
        $email->save();

        $email->saveAssociations($request);
    }
}
