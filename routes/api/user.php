<?php

use App\Http\Controllers\HotspotController;
use App\Http\Controllers\PackagesController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaystackController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\UserDashboardController;
use App\Http\Controllers\UsersController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

Route::middleware(['auth:sanctum', 'user'])->group(function () {
    Route::get('/dashboard', function () {
        Gate::authorize('view-user-dashboard');
        return response()->json(['message' => 'User dashboard']);
    });

    // Packages
    Route::get('/my-packages', [SubscriptionController::class, 'index']);

    Route::post('/paystack/initialize', [PaystackController::class, 'initialize']);
    Route::get('/paystack/verify/{reference}', [PaystackController::class, 'verify']);


    // Subscription Management
    Route::get('/subscriptions', [SubscriptionController::class, 'subscriptions']);
    Route::get('/subscriptions/{id}', [SubscriptionController::class, 'show']);
    Route::post('/subscribe-to-package', [SubscriptionController::class, 'store']);
    Route::post('/subscriptions/{id}/cancel', [SubscriptionController::class, 'cancel']);
    Route::post('/subscriptions/{id}/renew', [SubscriptionController::class, 'renew']);
    Route::get('/subscriptions/data-usage', [SubscriptionController::class, 'dataUsage']);

    Route::get('/hotspot-info', [SubscriptionController::class, 'hotspotInfo']);

    Route::get('/subscriptions/by-reference/{reference}', [SubscriptionController::class, 'showByReference']);

    // current plan / subscription / package
    Route::get('/user-current-package', [PackagesController::class, 'currentPackage']);

    // account
    Route::get('/my-account', [UsersController::class, 'authUser']);
    Route::post('/update-profile', [UsersController::class, 'update']);
    Route::post('/update-password', [UsersController::class, 'updatePassword']);
    Route::post('/upload-avatar', [UsersController::class, 'uploadAvatar']);

    // Payments
    Route::get('/get-user-payments', [PaymentController::class, 'index']);

    Route::get('/user/dashboard', [UserDashboardController::class, 'dashboard']);

    Route::get('/user/connected-devices', [UserDashboardController::class, 'connectedDevices']);
    Route::post('/user/disconnect-devices', [UserDashboardController::class, 'disconnectDevices']);
});

Route::middleware('web')->group(function () {
    Route::get('/hotspot/status', [HotspotController::class, 'status']);
});
