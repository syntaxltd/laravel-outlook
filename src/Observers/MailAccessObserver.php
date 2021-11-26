<?php

namespace Syntax\LaravelMailIntegration\Observers;

use Syntax\LaravelMailIntegration\Models\MailAccessToken;
use Throwable;

class MailAccessObserver
{
    /**
     * Handle the MailAccessToken "created" event.
     *
     * @param MailAccessToken $mailAccessToken
     * @return void
     * @throws Throwable
     */
    public function created(MailAccessToken $mailAccessToken)
    {
//        CentralMail::query()->updateOrCreate(['tenant_id' => tenant('id'), 'email' => $this->getEmail($mailAccessToken)]);
    }

    /**
     * Handle the MailAccessToken "deleted" event.
     *
     * @param MailAccessToken $mailAccessToken
     * @return void
     * @throws Throwable
     */
    public function deleted(MailAccessToken $mailAccessToken)
    {
//        CentralMail::query()->where(['tenant_id' => tenant('id'), 'email' => $this->getEmail($mailAccessToken)])->delete();
    }

    /**
     * @throws Throwable
     */
    private function getEmail(MailAccessToken $token): string
    {
        $email = $token->email;
//        if ($token->type == 'outlook') {
//            $service = LaravelMailIntegration::service('outlook', $token->partner_user_id);
//            $email = $service->auth()->user($service->getGraphClient())->getId();
//        }
//
        return $email;
    }
}
