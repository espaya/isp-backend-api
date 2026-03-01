<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\PaymentAuthorization;
use App\Models\Profile;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserUsage;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;

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

    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,gif,webp',
                'max:2048' // 2MB
            ]
        ], [
            'avatar.required' => 'Please select an image to upload.',
            'avatar.image' => 'The uploaded file must be an image.',
            'avatar.mimes' => 'Only JPG, JPEG, PNG, GIF, and WEBP images are allowed.',
            'avatar.max' => 'The image size must not exceed 2MB.'
        ]);

        try {
            $profile = Profile::where('user_id', Auth::id())->first();

            if (!$profile) {
                return response()->json(['message' => 'User profile not found!'], 404);
            }

            // Delete old avatar
            if ($profile->avatar && Storage::disk('public')->exists($profile->avatar)) {
                Storage::disk('public')->delete($profile->avatar);
            }

            // Store new avatar
            $path = $request->file('avatar')->store('avatars', 'public');

            $profile->avatar = $path;
            $profile->save();

            return response()->json([
                'message' => 'Avatar uploaded successfully',
                'avatar' => asset('storage/' . $path)
            ]);
        } catch (\Exception $ex) {
            Log::error($ex->getMessage());
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }

    public function view($id)
    {
        try {
            $user = User::with(['subscriptions.payment', 'profile'])->where('id', $id)->first();

            if (!$user) {
                return response()->json(['message' => 'User not found!'], 404);
            }

            return response()->json($user, 200);
        } catch (Exception $ex) {
            Log::error($ex->getMessage());
            return response()->json(['message' => 'An unexpected error occurred'], 500);
        }
    }

    public function updateByAdmin(Request $request, $id)
    {

        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9](?:[a-zA-Z0-9._]{1,18}[a-zA-Z0-9])$/',
                "unique:users,name,$id",
            ],

            'email' => [
                'required',
                'email:rfc,dns',
                "unique:users,email,$id",
            ],

            'role' => [
                'required',
                'in:user,admin',
            ],

            'status' => [
                'nullable',
                'in:active,suspended',
            ],

            'phone' => [
                'nullable',
                function ($attribute, $value, $fail) {

                    $clean = preg_replace('/\s+/', '', $value);

                    if (!preg_match('/^\+?[0-9]{7,15}$/', $clean)) {
                        $fail('Phone number must be valid internationally.');
                    }
                }
            ],
            'password' => [
                'nullable',
                'confirmed',
                'string',
                Password::min(8)
                    ->mixedCase()
                    ->letters()
                    ->numbers()
                    ->symbols()
                    ->uncompromised()
            ]

        ], [
            /* ================= NAME ================= */
            'name.required' => 'Customer name is required.',
            'name.max' => 'Name cannot exceed 255 characters.',
            'name.regex' =>
            'Name may contain letters, numbers, dots or underscores only.',
            'name.unique' =>
            'This username is already taken.',

            /* ================= EMAIL ================= */
            'email.required' => 'Email address is required.',
            'email.email' => 'Enter a valid email address.',
            'email.unique' => 'This email already exists.',

            /* ================= ROLE ================= */
            'role.required' => 'User role must be selected.',
            'role.in' => 'Invalid user role selected.',

            /* ================= STATUS ================= */
            'status.in' => 'Invalid account status.',

            /* ================= PHONE ================= */
            'phone.regex' =>
            'Phone must be a valid local or international number.',
            'phone.max' =>
            'Phone number is too long.',

            /* ================= PASSWORD ================= */
            'password.confirmed' => 'Passwords do not match',
            'password.string' => 'Invalid password format',

        ]);

        try {

            DB::beginTransaction();

            $user = User::findOrFail($id);

            /*
            |--------------------------------------------------------------------------
            | USER UPDATE
            |--------------------------------------------------------------------------
            */
            $user->fill([
                'name'   => $request->name,
                'email'  => $request->email,
                'role'   => $request->role,
                'status' => $request->status,
            ]);

            /*
            |--------------------------------------------------------------------------
            | PASSWORD UPDATE (ONLY IF PROVIDED)
            |--------------------------------------------------------------------------
            */
            if ($request->filled('password')) {

                // Optional: prevent same password update
                if (!Hash::check($request->password, $user->password)) {
                    $user->password = Hash::make($request->password);
                }
            }

            $userChanged = $user->isDirty();

            if ($userChanged) {
                $user->save();
            }

            /*
            |--------------------------------------------------------------------------
            | PROFILE UPDATE
            |--------------------------------------------------------------------------
            */
            $profile = $user->profile()->firstOrNew([
                'user_id' => $user->id
            ]);

            $profile->fill([
                'phone'   => $request->phone,
                'address' => $request->address,
            ]);

            $profileChanged = $profile->isDirty();

            if ($profileChanged) {
                $profile->save();
            }

            /*
            |--------------------------------------------------------------------------
            | CHECK IF NOTHING CHANGED
            |--------------------------------------------------------------------------
            */
            if (!$userChanged && !$profileChanged) {

                DB::rollBack();

                return response()->json([
                    'message' => 'No changes were made.'
                ], 200);
            }

            DB::commit();

            return response()->json([
                'message' => 'User updated successfully',
                'changes' => [
                    'user' => $user->getChanges(),
                    'profile' => $profile->getChanges(),
                ]
            ]);
        } catch (Exception $ex) {

            DB::rollBack();

            Log::error('Admin user update failed: ' . $ex->getMessage());

            return response()->json([
                'message' => 'An unexpected error occurred'
            ], 500);
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();

        try {
            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'message' => 'User not found!'
                ], 404);
            }

            $user->delete();

            DB::commit();

            return response()->json([
                'message' => 'User deleted successfully'
            ], 200);
        } catch (\Exception $ex) {

            DB::rollBack();

            Log::error($ex->getMessage());

            return response()->json([
                'message' => 'An unexpected error occurred'
            ], 500);
        }
    }
}
