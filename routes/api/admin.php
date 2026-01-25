<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/admin/dashboard', function () {
        Gate::authorize('view-admin-dashboard');
        return response()->json(['message' => 'Admin dashboard']);
    });
});

