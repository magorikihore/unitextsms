<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\SmsController;
use App\Http\Middleware\AuthenticateApiCredential;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(AuthenticateApiCredential::class)->group(function () {
    Route::get('/me', [AccountController::class, 'me']);
    Route::post('/sms', [SmsController::class, 'send']);
    Route::get('/sms/{uuid}', [SmsController::class, 'show']);
});
