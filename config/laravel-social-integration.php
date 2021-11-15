<?php

use App\Models\Attachment;

return [
    'default' => ['gmail', 'outlook'],

    'routes' => [
        'prefix' => '',

        'middleware' => ['web'],
    ],

    'services' => [
        'outlook' => [
            'appId' => env('OAUTH_APP_ID', null),

            'appSecret' => env('OAUTH_APP_SECRET', null),

            'redirectUri' => env('OAUTH_REDIRECT_URI', null),

            'scopes' => env('OAUTH_SCOPES', null),

            'authority' => env('OAUTH_AUTHORITY', 'https://login.microsoftonline.com/common'),

            'authorizeEndpoint' => env('OAUTH_AUTHORIZE_ENDPOINT', '/oauth2/v2.0/authorize'),

            'tokenEndpoint' => env('OAUTH_TOKEN_ENDPOINT', '/oauth2/v2.0/token'),
        ],

        'gmail' => [
            'project_id' => env('GOOGLE_PROJECT_ID'),

            'client_id' => env('GOOGLE_CLIENT_ID'),

            'client_secret' => env('GOOGLE_CLIENT_SECRET'),

            'redirect_url' => env('GOOGLE_REDIRECT_URI', '/'),

            'state' => null,

            'scopes' => [
                'readonly',
                'modify',
            ],

            'access_type' => 'offline',

            'approval_prompt' => 'force',

            'allow_multiple_credentials' => env('GOOGLE_ALLOW_MULTIPLE_CREDENTIALS', false),

            'allow_json_encrypt' => env('GOOGLE_ALLOW_JSON_ENCRYPT', false),
        ],
    ],

    'attachments' => [
        'store_locally' => false,
        'attachment_model' => Attachment::class
    ]
];
