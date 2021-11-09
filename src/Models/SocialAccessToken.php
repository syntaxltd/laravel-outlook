<?php

namespace Syntax\LaravelSocialIntegration\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SocialAccessToken extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'access_token', 'refresh_token', 'type', 'expires_at', 'partner_user_id',
    ];
}
