<?php

use App\Http\Controllers\AdminPaymentsController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\PackagesController;
use App\Http\Controllers\UsersController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('/admin/dashboard', function () {
        Gate::authorize('view-admin-dashboard');
        return response()->json(['message' => 'Admin dashboard']);
    });

    // Users
    Route::get('/get-users', [UsersController::class, 'index']);

    // Device
    Route::post('/add-device', [DeviceController::class, 'store']);
    Route::get('/all-devices', [DeviceController::class, 'index']);
    Route::get('/device-stats/{id}', [DeviceController::class, 'stats']);
    Route::get('/device-cards-stats', [DeviceController::class, 'cardStats']);

    // Packages 
    Route::get('/all-packages', [PackagesController::class, 'index']);
    Route::post('/add-package', [PackagesController::class, 'store']);
    Route::get('/single-package/{id}', [PackagesController::class, 'view']);
    Route::put('/update-package/{id}', [PackagesController::class, 'update']);
    Route::patch('/packages/{id}/toggle', [PackagesController::class, 'toggleStatus']);
    Route::delete('/delete-package/{id}', [PackagesController::class, 'destroy']);

    // Payments Listing
    Route::get('/all-payments', [AdminPaymentsController::class, 'index']);

});
