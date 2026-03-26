<?php

use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/api/auth.php';
require __DIR__.'/api/admin.php';
require __DIR__.'/api/user.php';

    // Packages
    Route::get('/frontend-packages', [SubscriptionController::class, 'index']);
