<?php

use App\Http\Controllers\DeviceController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

Route::middleware(['auth:sanctum', 'web'])->group(function () {
    Route::get('/admin/dashboard', function () {
        Gate::authorize('view-admin-dashboard');
        return response()->json(['message' => 'Admin dashboard']);
    });

    Route::post('/add-device', [DeviceController::class, 'store']);
    Route::get('/all-devices', [DeviceController::class, 'index']);
    Route::get('/device-stats/{id}', [DeviceController::class, 'stats']);
    Route::get('/device-cards-stats', [DeviceController::class, 'cardStats']);
});
