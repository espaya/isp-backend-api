<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\AuthController;


Route::get('/', function () {
    return view('welcome');
});

Route::get('/sanctum/csrf-cookie', function (Request $request) {
    return response()->noContent();
});

Route::middleware(['web'])->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/logout', [AuthController::class, 'logout']);
});
