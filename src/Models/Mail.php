<?php

namespace Syntax\LaravelMailIntegration\Models;

use App\Helpers\ConvertArray;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Property;
use App\Traits\HasAttachment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * @property Collection $companies
 * @property Collection $contacts
 * @property Collection $properties
 * @property Collection $deals
 * @property int $token_id
 * @property string|null $email_id
 * @property string|null $thread_id
 * @property array|string $data
 * @property int $parentable_id
 * @property string $parentable_type
 * @property Collection|array $threads
 * @property Contact $parentable
 * @property Carbon $created_at
 * @property int $id
 * */
class Mail extends Model
{
    use HasAttachment;

    /**
     * @var string[]
     */
    public array $associables = ['properties', 'contacts', 'companies', 'deals'];

    protected $fillable = [
        'parentable_id', 'parentable_type', 'email_id', 'thread_id', 'history_id', 'token_id', 'data', 'created_at', 'updated_at', 'content',
    ];

    protected $casts = [
        'data' => 'array',
    ];

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
     * Get the table that this association is attached to.
     *
     * @return MorphTo
     */
    public function parentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Save note associations.
     *
     * @param Request $request
     */
    public function saveAssociations(Request $request): void
    {
        $this->contacts()->sync((new ConvertArray())->convertToArray(array_merge($request->input('associations.contacts'), $request->input('contact'))));
        $this->companies()->sync((new ConvertArray())->convertToArray($request->input('associations.companies')));
        $this->properties()->sync((new ConvertArray())->convertToArray($request->input('associations.properties')));
        $this->deals()->sync((new ConvertArray())->convertToArray($request->input('associations.deals')));
    }

    /**
     * Get all of the contacts that are assigned this mail.
     */
    public function contacts(): MorphToMany
    {
        return $this->morphedByMany(Contact::class, 'mailable');
    }

    /**
     * Get all of the companies that are assigned this mail.
     */
    public function companies(): MorphToMany
    {
        return $this->morphedByMany(Company::class, 'mailable');
    }

    /**
     * Get all of the properties that are assigned this mail.
     */
    public function properties(): MorphToMany
    {
        return $this->morphedByMany(Property::class, 'mailable');
    }

    /**
     * Get all of the deals that are assigned this mail.
     */
    public function deals(): MorphToMany
    {
        return $this->morphedByMany(Deal::class, 'mailable');
    }

    /**
     * Get the email provider access token.
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(MailAccessToken::class, 'token_id');
    }
}
