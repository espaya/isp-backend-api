<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:register')->post('/register', [AuthController::class, 'register']);
Route::middleware('throttle:login')->post('/login', [AuthController::class, 'login'])->name('login');
Route::middleware('throttle:login')->get('/login', function () {
    return response()->json(['message' => 'Please use POST method to login.'], 405);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});
