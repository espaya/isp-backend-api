<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SubscriptionController;

require __DIR__.'/api/auth.php';
require __DIR__.'/api/admin.php';
require __DIR__.'/api/user.php';

    // Packages
    Route::get('/frontend-packages', [SubscriptionController::class, 'index']);
