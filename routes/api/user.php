<?php

use App\Http\Controllers\PaystackController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

Route::middleware(['auth:sanctum', 'web', 'user'])->group(function () {
    Route::get('/dashboard', function () {
        Gate::authorize('view-user-dashboard');
        return response()->json(['message' => 'User dashboard']);
    });

    // Packages
    Route::get('/my-packages', [SubscriptionController::class, 'index']);

    Route::post('/paystack/initialize', [PaystackController::class, 'initialize']);
    Route::get('/paystack/verify/{reference}', [PaystackController::class, 'verify']);
    Route::get('/paystack/callback', [PaystackController::class, 'callback'])->name('paystack.callback');


    // Subscription Management
    Route::get('/subscriptions', [SubscriptionController::class, 'subscriptions']);
    Route::get('/subscriptions/{id}', [SubscriptionController::class, 'show']);
    Route::post('/subscribe-to-package', [SubscriptionController::class, 'store']);
    Route::post('/subscriptions/{id}/cancel', [SubscriptionController::class, 'cancel']);
    Route::post('/subscriptions/{id}/renew', [SubscriptionController::class, 'renew']);
    Route::get('/subscriptions/data-usage', [SubscriptionController::class, 'dataUsage']);
});
