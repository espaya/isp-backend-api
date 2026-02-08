<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;

/**
 * 
 * This controller is responsible for handling user-related operations, such as retrieving user information and managing user authentication. It includes methods for listing users and fetching the authenticated user's details. The controller also incorporates error handling to ensure that any exceptions are logged appropriately, providing a robust and reliable user management system.
 * 
 * */

class UsersController extends Controller
{
    public function index()
    {
        try {
            $user = User::orderBy('created_at', 'desc')->paginate(10);

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
            $user = User::with('profile')->where('id', Auth::id())->first();
            if (!$user) return response()->json(['message' => 'Your account was not found'], 500);
            return response()->json($user, 200);
        } catch (Exception $ex) {
            Log::info($ex->getMessage());
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }

    public function update(Request $request)
    {
        $id = Auth::id(); // Ensure the user can only update their own information

        $request->validate([
            'name' => [
                'sometimes',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9](?:[a-zA-Z0-9._]{1,18}[a-zA-Z0-9])$/',
                'unique:users,name,' . $id,
            ],

            'email' => [
                'sometimes',
                'email:rfc,dns',
                'max:255',
                'unique:users,email,' . $id,
            ],

            'address' => [
                'sometimes',
                'string',
                'max:255',
            ],

            'phone' => [
                'sometimes',
                'string',
                'max:20',
                'regex:/^(\+233|233|0)(2[0-9]|5[0-9])[0-9]{7}$/',
                'unique:profiles,phone,' . $id . ',user_id',
            ],


        ], [
            // NAME
            'name.regex'   => 'Name must be 3–20 characters and may contain letters, numbers, dot (.) and underscore (_).',
            'name.unique'  => 'This username is already taken.',

            // EMAIL
            'email.email'  => 'Please enter a valid email address.',
            'email.unique' => 'The email has already been taken by another user.',

            // PHONE
            'phone.regex'  => 'Phone number must be valid (e.g. 024XXXXXXX, 23324XXXXXXX or +23324XXXXXXX).',
            'phone.unique' => 'This phone number has already been used by another user.',

            // ADDRESS
            'address.max'  => 'Address must not exceed 255 characters.',
        ]);

        try {
            DB::beginTransaction();

            //  it returned true
            $user = User::find($id);

            if (!$user) {
                return response()->json(['message' => 'User not found'], 404);
            }

            // Update only if field is provided
            $user->fill($request->only(['name', 'email']));

            if ($user->isDirty()) {
                $user->save();
            }

            Profile::updateOrCreate(
                ['user_id' => $id],
                [
                    'phone' => $request->phone,
                    'address' => $request->address,
                ]
            );

            DB::commit();

            Log::info(User::with('profile')->find($id));

            return response()->json([
                'message' => 'Profile updated successfully',
                'user' => $user->fresh('profile')
            ], 200);
        } catch (Exception $ex) {
            DB::rollBack();
            Log::error($ex->getMessage());

            return response()->json([
                'message' => 'An unexpected error occurred'
            ], 500);
        }
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'password' => [
                'required',
                'confirmed', // ✅ expects password_confirmation
                Password::min(8)
                    ->mixedCase()
                    ->letters()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
            ],
        ], [
            'password.confirmed' => 'Passwords do not match.',
            'password.requird' => 'Password is required',
        ]);

        try {
            DB::beginTransaction();

            $user = User::find(Auth::id());

            if (!$user) {
                return response()->json(['message' => 'User not found!'], 404);
            }

            $user->password = $request->password; // ✅ auto-hashed because of casts()

            if ($user->isDirty('password')) {
                $user->save();
            }

            DB::commit();

            return response()->json([
                'message' => 'Password updated successfully'
            ], 200);
        } catch (Exception $ex) {
            DB::rollBack();
            Log::error($ex->getMessage());

            return response()->json([
                'message' => 'An unexpected error occurred'
            ], 500);
        }
    }
}
