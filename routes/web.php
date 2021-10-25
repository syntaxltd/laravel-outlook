<?php

use Dytechltd\LaravelOutlook\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => 'web', 'as' => 'oauth.'], function () {
    Route::get('/oauth/login', [LoginController::class, 'login'])->name('login');
    Route::get('/oauth/callback', [LoginController::class, 'callback'])->name('callback');
    Route::get('/oauth/logout', [LoginController::class, 'logout'])->name('logout');
});
