<?php

use Illuminate\Support\Facades\Route;
use Syntax\LaravelOutlook\Http\Controllers\Auth\LoginController;

Route::middleware('web')->group(function () {
    Route::group(['prefix' => '/oauth', 'as' => 'oauth.'], function () {
        Route::get('/login', [LoginController::class, 'login'])->name('login');
        Route::get('/callback', [LoginController::class, 'callback'])->name('callback');
        Route::get('/logout', [LoginController::class, 'logout'])->name('logout');
    });
});
