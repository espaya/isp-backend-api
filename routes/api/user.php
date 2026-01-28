<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

Route::middleware(['auth:sanctum', 'web', 'user'])->group(function () {
    Route::get('/dashboard', function () {
        Gate::authorize('view-user-dashboard');
        return response()->json(['message' => 'User dashboard']);
    });
});
