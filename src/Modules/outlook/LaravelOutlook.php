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
use Syntax\LaravelSocialIntegration\Models\SocialAccessMail;
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
        $mails = SocialAccessMail::query()->distinct()->get(['thread_id']);
        $mails->each(function (SocialAccessMail $email) {
            $threads = $this->getGraphClient()
                ->createRequest('GET', "/me/messages?\$filter=conversationId eq '$email->thread_id'")
                ->setReturnType(ChatMessage::class)
                ->execute();

            collect($threads)->each(function (ChatMessage $chatMessage) use ($email) {
                /** @var SocialAccessMail $socialMail */
                $socialMail = SocialAccessMail::query()->firstWhere('thread_id', $email->thread_id);
                $mailProperties = $chatMessage->getProperties();
                SocialAccessMail::query()->firstOrCreate(['email_id' => $chatMessage->getId()], [
                    'parentable_id' => $socialMail->parentable_id,
                    'parentable_type' => $socialMail->parentable_type,
                    'thread_id' => $mailProperties['conversationId'],
                    'token_id' => $socialMail->token_id,
                    'created_at' => $mailProperties['createdDateTime'],
                    'updated_at' => $mailProperties['lastModifiedDateTime'],
                    'data' => [
                        'contact' => $socialMail->data['contact'],
                        'from' => $chatMessage->getFrom(),
                        'subject' => $chatMessage->getSubject(),
                        'content' => $chatMessage->getBody(),
                    ],
                ]);
            });
        });

        return collect([]);
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
