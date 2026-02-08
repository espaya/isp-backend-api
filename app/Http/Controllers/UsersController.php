<?php

namespace App\Http\Controllers;

use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class UsersController extends Controller
{
    public function index()
    {
        try {
            $user = User::paginate(10);

            if ($user->isEmpty()) {
                return response()->json(['message' => 'User(s) not found'], 404);
            }

            return response()->json($user, 200);
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
        }
    }

    public function authUser()
    {
        try {
            $user = Auth::user();
            if (!$user) return response()->json(['message' => 'Your account was not found'], 500);
            return response()->json($user, 200);
        } catch (Exception $ex) {
            Log::info($ex->getMessage());
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }
}
