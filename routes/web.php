<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;


Route::get('/sanctum/csrf-cookie', function (Request $request) {
    return response()->noContent();
});



// Route::middleware(['web'])->group(function () {
// Route::middleware([
//     EnsureFrontendRequestsAreStateful::class,
//     'throttle:login',
// ])->post('/login', [AuthController::class, 'login'])->name('login');
// });
