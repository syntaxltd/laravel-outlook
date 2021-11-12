<?php


namespace Syntax\LaravelSocialIntegration\Models;


use Illuminate\Database\Eloquent\Model;

class SocialAccessEmail extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'social_access_token_id','email_id', 'thread_id', 'to', 'from', 'cc', 'bcc', 'subject', 'message'
    ];
}