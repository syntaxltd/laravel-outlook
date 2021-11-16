<?php

namespace Syntax\LaravelSocialIntegration\Models;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Property;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;

/**
 * @property Collection $companies
 * @property Collection $contacts
 * @property Collection $properties
 * @property Collection $deals
 * @property int $token_id
 * @property string $email_id
 * @property string $thread_id
 * @property string|array $data
 * @property int $parentable_id
 * @property string $parentable_type
 * */
class SocialAccessMail extends Model
{
    /**
     * @var string[]
     */
    public $associables = ['properties', 'contacts', 'companies', 'deals'];
    /**
     *
     * @var array
     */
    protected $appends = ['associations'];

    /**
     *
     * @return array
     */
    public function getAssociationsAttribute(): array
    {
        return [
            'companies' => $this->companies,
            'contacts' => $this->contacts,
            'properties' => $this->properties,
            'deals' => $this->deals,
        ];
    }

    /**
     * Get all of the companies that are assigned this mail.
     */
    public function companies(): MorphToMany
    {
        return $this->morphedByMany(Company::class, 'social_access_mailable');
    }

    /**
     * Get all of the contacts that are assigned this mail.
     */
    public function contacts(): MorphToMany
    {
        return $this->morphedByMany(Contact::class, 'social_access_mailable');
    }

    /**
     * Get all of the properties that are assigned this mail.
     */
    public function properties(): MorphToMany
    {
        return $this->morphedByMany(Property::class, 'social_access_mailable');
    }

    /**
     * Get all of the deals that are assigned this mail.
     */
    public function deals(): MorphToMany
    {
        return $this->morphedByMany(Deal::class, 'social_access_mailable');
    }

    /**
     * Get the table that this association is attached to.
     *
     * @return MorphTo
     */
    public function parentable(): MorphTo
    {
        return $this->morphTo();
    }
}