<?php


namespace Syntax\LaravelSocialIntegration\Modules\gmail;

use App\Models\PartnerUser;
use Google_Service_Gmail_Message;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Syntax\LaravelSocialIntegration\Contracts\SocialClient;
use Syntax\LaravelSocialIntegration\Models\SocialAccessMail;
use Syntax\LaravelSocialIntegration\Models\SocialAccessToken;
use Syntax\LaravelSocialIntegration\Modules\gmail\services\GmailConnection;
use Syntax\LaravelSocialIntegration\Modules\gmail\services\Mail;
use Throwable;

class LaravelGmail extends GmailConnection implements SocialClient
{
    public function auth(): AuthClient
    {
        return new AuthClient();
    }

    /**
     * @param string $id
     *
     * @return Google_Service_Gmail_Message
     */
    public function get(string $id): Google_Service_Gmail_Message
    {
        return $this->service->users_messages->get('me', $id);
    }

    public function all(): Collection
    {
        $client = SocialAccessToken::query()->where('partner_user_id', Auth::id())->where('type', 'gmail')->pluck('id');

        return SocialAccessMail::query()->whereIn('token_id', $client)->get();
    }

    /**
     * Sends a new email
     *
     * @param Request $request
     * @return Mail
     * @throws Throwable
     */
    public function send(Request $request): Mail
    {
        /** @var PartnerUser $user */
        $user = auth('partneruser')->user();

        $mail = new Mail();
        $mail->to($this->getContacts($request));
        $mail->from($user->email, $user->name);
        $mail->cc($request->input('cc'));
        $mail->bcc($request->input('bcc'));
        $mail->subject($request->input('subject'));
        $mail->message($request->input('content'));

        if (!is_null($request->input('attachments'))) {
            $mail->attach($request->input('attachments'));
        }
        $mail->send();

        $this->storeMail($mail, $request);

        return $mail;
    }


    /**
     * Sends a new email
     *
     * @param Request $request
     * @return Mail
     * @throws Throwable
     */
    public function reply(Request $request): Mail
    {
        $mailable = $this->get($request->input('id'));
        $mail = new Mail($mailable);
        $mail->reply();

        return $mail;
    }

    private function getContacts(Request $request): array
    {
        return collect($request->input('recipients'))->filter()->map(function ($item) {
            return $item['email'];
        })->toArray();
    }

    private function storeMail(Mail $mail, Request $request): void
    {
        /** @var SocialAccessToken $token */
        $token = SocialAccessToken::query()->where([
            'partner_user_id' => auth('partneruser')->id(),
            'type' => 'gmail',
        ])->first();

        $email = new SocialAccessMail;
        $email->parentable_id = $request->input('parent.id');
        $email->parentable_type = 'App\Models\\' . Str::ucfirst($request->input('parent.type'));
        $email->email_id = $mail->id;
        $email->thread_id = $mail->threadId;
        $email->token_id = $token->id;
        $email->data = [
            'to' => $request->input('contact'),
            'from' => $token->email,
            'subject' => $mail->subject,
            'message' => $mail->message,
            'labels' => $mail->labels
        ];
        $email->save();

        $email->saveAssociations($request);
    }
}
