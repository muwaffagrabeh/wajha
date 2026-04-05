<?php

use App\Http\Controllers\Api\AliChatController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessController;
use App\Http\Controllers\Api\OtpController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\StatsController;
use Illuminate\Support\Facades\Route;

// Public
Route::post('/auth/otp/send', [OtpController::class, 'send']);
Route::post('/auth/otp/verify', [OtpController::class, 'verify']);
Route::post('/auth/link-session', [AuthController::class, 'linkSession']);
Route::post('/guest/ali/chat', [App\Http\Controllers\Api\GuestAliController::class, 'send']);

// Onboarding
Route::post('/onboarding/message', [App\Http\Controllers\Api\OnboardingController::class, 'message']);
Route::post('/onboarding/reset', [App\Http\Controllers\Api\OnboardingController::class, 'reset']);
Route::get('/onboarding/session/{token}', [App\Http\Controllers\Api\OnboardingController::class, 'session']);

// Protected
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn($r) => $r->user());

    Route::get('/businesses', [BusinessController::class, 'index']);

    Route::get('/businesses/{businessId}/services', [ServiceController::class, 'index']);
    Route::post('/businesses/{businessId}/services', [ServiceController::class, 'store']);
    Route::put('/businesses/{businessId}/services/{serviceId}', [ServiceController::class, 'update']);
    Route::patch('/businesses/{businessId}/services/{serviceId}/toggle', [ServiceController::class, 'toggle']);

    Route::get('/businesses/{businessId}/stats', [StatsController::class, 'index']);

    Route::post('/ali/chat', [AliChatController::class, 'send']);
});
