<?php

namespace Syntax\LaravelSocialIntegration\Traits;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Syntax\LaravelSocialIntegration\Models\SocialAccessMail;

trait HasMails
{
    /**
     * Get mails related to model
     *
     * @return MorphToMany
     */
    public function mails(): MorphToMany
    {
        return $this->morphToMany(SocialAccessMail::class, 'social_access_mailable');
    }

}