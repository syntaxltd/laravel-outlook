<?php

use Illuminate\Support\Facades\Route;
use Syntax\LaravelSocialIntegration\Http\Controllers\Auth\LoginController;
use Syntax\LaravelSocialIntegration\Http\Controllers\MailController;

Route::middleware('web')->group(function () {
    Route::group(['prefix' => '/oauth', 'as' => 'oauth.'], function () {
        Route::get('/login/{client}', [LoginController::class, 'login'])
            ->where('client', 'gmail|outlook')
            ->name('login');
        Route::get('/callback/{client}', [LoginController::class, 'callback'])
            ->where('client', 'gmail|outlook')
            ->name('callback');
        Route::get('/message/create/{client}', [MailController::class, 'create'])
            ->where('client', 'gmail|outlook')
            ->name('message.create');

        Route::get('/logout', [LoginController::class, 'logout'])->name('logout');
    });
});
