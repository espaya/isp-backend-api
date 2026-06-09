<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\DeviceController;

require __DIR__ . '/api/auth.php';
require __DIR__ . '/api/admin.php';
require __DIR__ . '/api/user.php';

// Packages
Route::get('/frontend-packages', [SubscriptionController::class, 'index']);

Route::get('/ping', [DeviceController::class, 'pingDevice']);
