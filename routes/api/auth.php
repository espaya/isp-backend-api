<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;


Route::middleware('throttle:register')->post('/register', [AuthController::class, 'register']);

Route::middleware([
    EnsureFrontendRequestsAreStateful::class,
    'throttle:login',
])->post('/login', [AuthController::class, 'login'])->name('login');



Route::middleware(['auth:sanctum', 'web'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);
});
