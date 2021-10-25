<?php

use Dytechltd\LaravelOutlook\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;
use Microsoft\Graph\Graph;
use Microsoft\Graph\Http\GraphResponse;

Route::middleware('web')->group(function () {
    Route::group(['prefix' => '/oauth', 'as' => 'oauth.'], function () {
        Route::get('/login', [LoginController::class, 'login'])->name('login');
        Route::get('/callback', [LoginController::class, 'callback'])->name('callback');
        Route::get('/logout', [LoginController::class, 'logout'])->name('logout');
    });

    Route::get('/send', function () {
        /**
         * @var Graph $graph
         */
        $graph = app('laravel-outlook')->getGraphClient();

        /**
         * @var GraphResponse $response
         */
        $response = $graph->createRequest('POST', '/me/sendMail')->attachBody([
            'saveToSentItems' => true,
            'message' => [
                "subject" => "Meet for lunch?",
                "body" => [
                    "contentType" => "Text",
                    "content" => "The new cafeteria is open."
                ],
                "toRecipients" => [
                    [
                        'emailAddress' => [
                            'name' => 'Dennis Mwea',
                            'address' => 'mweadennis2@gmail.com'
                        ]
                    ]
                ],
                "attachments" => [
                    [
                        "@odata.type" => "#microsoft.graph.fileAttachment",
                        "name" => "arrow-left.png",
                        "contentType" => "image/png",
                        "contentBytes" => base64_encode(file_get_contents(public_path('arrow-left.png'))),
                    ]
                ]
            ],
        ])->execute();

        return back();
    })->name('send');
});
