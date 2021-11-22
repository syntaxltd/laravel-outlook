<?php

namespace Syntax\LaravelMailIntegration\Traits;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Syntax\LaravelMailIntegration\Models\Mail;

trait HasMails
{
    /**
     * Get mails related to model
     *
     * @return MorphToMany
     */
    public function mails(): MorphToMany
    {
        return $this->morphToMany(Mail::class, 'mailable');
    }

}