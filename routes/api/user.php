<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

Route::get('/dashboard', function () {
    Gate::authorize('view-user-dashboard');
    return response()->json(['message' => 'User dashboard']);
});
