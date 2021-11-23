<?php

namespace Syntax\LaravelMailIntegration\Observers;

use App\Models\CentralMail;
use Illuminate\Support\Facades\Log;
use Syntax\LaravelMailIntegration\Models\MailAccessToken;

class MailAccessObserver
{
    /**
     * Handle the MailAccessToken "created" event.
     *
     * @param MailAccessToken $mailAccessToken
     * @return void
     */
    public function created(MailAccessToken $mailAccessToken)
    {
        CentralMail::create([
            'tenant_id' => tenant('id'),
            'email' => $mailAccessToken->email
        ]);
    }

    /**
     * Handle the MailAccessToken "updated" event.
     *
     * @param MailAccessToken $mailAccessToken
     * @return void
     */
    public function updated(MailAccessToken $mailAccessToken)
    {
        //
    }

    /**
     * Handle the MailAccessToken "deleted" event.
     *
     * @param MailAccessToken $mailAccessToken
     * @return void
     */
    public function deleted(MailAccessToken $mailAccessToken)
    {
        CentralMail::where('tenant_id', tenant('id'))->where('email', $mailAccessToken->email)->delete();
    }

    /**
     * Handle the MailAccessToken "restored" event.
     *
     * @param MailAccessToken $mailAccessToken
     * @return void
     */
    public function restored(MailAccessToken $mailAccessToken)
    {
        //
    }

    /**
     * Handle the MailAccessToken "force deleted" event.
     *
     * @param MailAccessToken $mailAccessToken
     * @return void
     */
    public function forceDeleted(MailAccessToken $mailAccessToken)
    {
        //
    }
}
