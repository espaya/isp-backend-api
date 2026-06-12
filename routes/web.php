<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use App\Http\Controllers\PaystackController;

Route::get('/sanctum/csrf-cookie', function (Request $request) {
    return response()->noContent();
});

    Route::get('/paystack/callback', [PaystackController::class, 'callback'])->name('paystack.callback');


// Route::middleware(['web'])->group(function () {
// Route::middleware([
//     EnsureFrontendRequestsAreStateful::class,
//     'throttle:login',
// ])->post('/login', [AuthController::class, 'login'])->name('login');
// });

Route::get('/test-ping', function () {
    $ip = '192.168.10.1';
    exec("ping -n 2 " . escapeshellarg($ip), $output, $status);
    return response()->json([
        'ip' => $ip,
        'status' => $status === 0 ? 'online' : 'offline',
        'output' => $output
    ]);
});
