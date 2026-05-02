<?php

namespace App\Http\Controllers;

// use App\Mail\PaymentReceiptMail;
use App\Models\Packages;
use App\Models\Payment;
use App\Models\PaymentAuthorization;
use App\Models\Subscription;
use App\Models\User;
use App\Services\MikrotikService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use \Illuminate\Support\Str;
use App\Services\DeviceSelectorService;
use Illuminate\Support\Facades\Mail;

class PaystackController extends Controller
{
    public function initialize(Request $request)
    {
        // Preprocess card inputs
        $request->merge([
            'card_number' => str_replace(' ', '', $request->card_number ?? ''),
            'expiry' => str_replace(' ', '', $request->expiry ?? ''),
        ]);

        // Validate inputs
        $request->validate([
            'package_id' => ['required', 'exists:packages,id'],
            'payment_method' => ['required', 'in:card,mobile_money'],

            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'required_if:payment_method,mobile_money', 'email'],

            // Mobile money
            'phone' => [
                'nullable',
                'required_if:payment_method,mobile_money',
                'regex:/^(0|\+233)[245][0-9]{8}$/'
            ],
            'provider' => [
                'nullable',
                'required_if:payment_method,mobile_money',
                'in:mtn,telecel'
            ],

            // Card
            'card_number' => [
                'nullable',
                'required_if:payment_method,card',
                'regex:/^\d{16}$/'
            ],
            'expiry' => [
                'nullable',
                'required_if:payment_method,card',
                'regex:/^(0[1-9]|1[0-2])\/\d{2}$/'
            ],
            'cvv' => [
                'nullable',
                'required_if:payment_method,card',
                'digits_between:3,4',
            ],

        ], [
            'package_id.required' => 'Please select a package.',
            'package_id.exists' => 'Selected package does not exist.',

            'payment_method.required' => 'Please select a payment method.',
            'payment_method.in' => 'Invalid payment method selected.',

            'name.required' => 'Please enter your name.',
            'name.string' => 'Name must be a valid string.',
            'name.max' => 'Name cannot exceed 255 characters.',

            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',

            'phone.required_if' => 'Phone number is required for mobile money payments.',
            'phone.regex' => 'Please enter a valid Ghanaian phone number (e.g., 0241234567).',

            'provider.required_if' => 'Mobile money provider is required.',
            'provider.in' => 'Invalid mobile money provider.',

            'card_number.required_if' => 'Card number is required for card payments.',
            'card_number.regex' => 'Card number must be 16 digits (spaces are ignored).',

            'expiry.required_if' => 'Expiry date is required for card payments.',
            'expiry.regex' => 'Expiry must be in MM/YY format.',

            'cvv.required_if' => 'CVV is required for card payments.',
            'cvv.digits_between' => 'CVV must be 3 or 4 digits.',
        ]);

        try {
            // Check card expiry if card payment
            if ($request->payment_method === 'card') {
                [$month, $year] = explode('/', $request->expiry);
                $year = '20' . $year;
                if (strtotime("$year-$month-01") < strtotime(date('Y-m-01'))) {
                    return back()->withErrors(['expiry' => 'Card has expired']);
                }
            }

            $package = Packages::findOrFail($request->package_id);
            $reference = 'ISP_' . uniqid();

            // Create a pending payment record
            Payment::create([
                'user_id' => Auth::id(),
                'package_id' => $package->id,
                'reference' => $reference,
                'amount' => $package->price * 100, // Paystack expects amount in kobo
                'status' => 'pending',
            ]);


            if ($request->payment_method === 'card') {
                // Initialize card transaction
                $payload = [
                    'email' => Auth::user()->email,
                    'amount' => $package->price * 100,
                    'reference' => $reference,
                    'channels' => ['card'],
                    'callback_url' => config('app.frontend_url') . '/api/paystack/callback', //config('app.frontend_url') . '/dashboard/payment/success',
                    'metadata' => [
                        'user_id' => Auth::id(),
                        'package_id' => $package->id,
                    ],
                ];

                $response = Http::withToken(config('services.paystack.secret_key'))
                    ->post(config('services.paystack.base_url') . '/transaction/initialize', $payload);

                $data = $response->json();

                if (!$response->ok() || !isset($data['data']['authorization_url'])) {
                    return response()->json(['message' => 'Paystack initialization failed', 'errors' => $data], 500);
                }

                return response()->json([
                    'authorization_url' => $data['data']['authorization_url'],
                    'reference' => $reference,
                ]);
            } elseif ($request->payment_method === 'mobile_money') {
                // Mobile money charge
                $payload = [
                    'email' => Auth::user()->email,
                    'amount' => $package->price * 100,
                    'reference' => $reference,
                    'currency' => 'GHS',
                    'mobile_money' => [
                        'phone' => $request->phone,
                        'provider' => strtolower($request->provider), // must match Paystack code
                    ],
                    'metadata' => [
                        'user_id' => Auth::id(),
                        'package_id' => $package->id,
                    ],
                ];

                $response = Http::withToken(config('services.paystack.secret_key'))
                    ->post('https://api.paystack.co/charge', $payload);

                $data = $response->json();

                if (!$response->ok() || !isset($data['data']['status'])) {
                    return response()->json(['message' => 'Paystack MoMo charge failed', 'errors' => $data], 500);
                }


                // For MoMo, status may be pay_offline; wait for webhook to confirm success
                return response()->json([
                    'reference' => $reference,
                    'status' => $data['data']['status'],
                    'display_text' => $data['data']['display_text'] ?? 'Check your phone to complete payment',
                ]);
            }
        } catch (Exception $ex) {
            Log::error($ex->getMessage() . 'on line' . $ex->getLine());
            return response()->json(['message' => $ex->getMessage()], 500);
        }
    }

    public function verify(Request $request, string $reference)
    {
        DB::beginTransaction();

        try {
            Log::info('Starting verification for reference: ' . $reference);

            // 1️⃣ Verify payment from Paystack
            $response = Http::withToken(config('services.paystack.secret_key'))
                ->get(config('services.paystack.base_url') . "/transaction/verify/{$reference}");

            Log::info('Paystack verification response status: ' . $response->status());

            if (!$response->ok()) {
                DB::rollBack();
                Log::error('Paystack verification HTTP failed for reference: ' . $reference);
                return response()->json(['message' => 'Paystack verification failed'], 400);
            }

            $data = $response->json()['data'] ?? null;

            if (!$data || ($data['status'] ?? 'failed') !== 'success') {
                DB::rollBack();
                Log::error('Payment not successful for reference: ' . $reference, ['data' => $data]);
                return response()->json([
                    'message' => $data['gateway_response'] ?? 'Payment not successful',
                    'status' => $data['status'] ?? 'failed'
                ], 400);
            }

            // 2️⃣ Extract metadata
            $metadata = $data['metadata'] ?? [];
            $userId = $metadata['user_id'] ?? null;
            $packageId = $metadata['package_id'] ?? null;

            if (!$userId || !$packageId) {
                DB::rollBack();
                Log::error('Missing metadata from Paystack for reference: ' . $reference);
                return response()->json(['message' => 'Missing metadata from Paystack'], 400);
            }

            // Check if payment already processed
            $existingPayment = Payment::where('reference', $reference)->first();
            if ($existingPayment && $existingPayment->status === 'success') {
                DB::commit();
                Log::info('Payment already verified for reference: ' . $reference);
                return response()->json(['message' => 'Payment already verified'], 200);
            }

            $payment = Payment::firstOrCreate(
                ['reference' => $reference],
                [
                    'user_id' => $userId,
                    'package_id' => $packageId,
                    'amount' => $data['amount'],
                    'status' => 'pending'
                ]
            );

            $package = Packages::findOrFail($packageId);
            $user = User::findOrFail($userId);

            $payment->update([
                'status' => 'success',
                'payload' => $data,
                'channel' => $data['channel'] ?? null,
                'gateway_response' => $data['gateway_response'] ?? null,
            ]);

            $password = Str::random(8);

            // 3️⃣ Create subscription
            $startsAt = now();

            $subscription = Subscription::create([
                'user_id' => $user->id,
                'package_id' => $package->id,
                'payment_id' => $payment->id,
                'starts_at' => $startsAt,
                'expires_at' => match ($package->type) {
                    'daily'   => $startsAt->copy()->addDay()->subSecond(),
                    'weekly'  => $startsAt->copy()->addDays(7)->subSecond(),
                    'monthly' => $startsAt->copy()->addDays(30)->subSecond(),
                    default   => throw new Exception('Invalid package type'),
                },
                'status' => 'active',
                'hotspot_password' => $password,
            ]);

            // Try to create Mikrotik user, but don't fail if it doesn't work
            try {
                $selector = new DeviceSelectorService();
                $device = $selector->selectBestDevice(
                    $request->latitude ?? 0,
                    $request->longitude ?? 0
                );

                if ($device && !empty($device->ip) && !empty($device->api_user) && !empty($device->api_password)) {
                    $mikrotik = new MikrotikService($device);
                    $mikrotik->createOrUpdateHotspotUser(
                        username: $user->email,
                        password: $password,
                        profile: $package->mikrotik_profile,
                        expiresAt: $subscription->expires_at
                    );
                    $device->increment('current_clients');
                }
            } catch (\Exception $e) {
                Log::warning('Mikrotik user creation failed but subscription is active: ' . $e->getMessage());
                // Don't rollback - subscription is still valid
            }

            $subscription->hotspot_password = $password;
            $subscription->save();

            $authorization = $data['authorization'] ?? null;
            if ($authorization && ($data['channel'] ?? null) === 'card') {
                PaymentAuthorization::updateOrCreate(
                    ['user_id' => $userId],
                    [
                        'authorization_code' => $authorization['authorization_code'],
                        'card_type' => $authorization['card_type'] ?? null,
                        'last4' => $authorization['last4'] ?? null,
                        'exp_month' => $authorization['exp_month'] ?? null,
                        'exp_year' => $authorization['exp_year'] ?? null,
                        'bank' => $authorization['bank'] ?? null,
                    ]
                );
            }

            DB::commit();
            Log::info('Payment verified and subscription activated for reference: ' . $reference);

            return response()->json([
                'message' => 'Payment verified and subscription activated',
                'subscription' => $subscription,
                'hotspot_password' => $password
            ], 200);
        } catch (\Exception $ex) {
            DB::rollBack();
            Log::error('Verification error: ' . $ex->getMessage() . ' on line ' . $ex->getLine() . ' File: ' . $ex->getFile());
            return response()->json(['message' => 'An unexpected error occurred: ' . $ex->getMessage()], 500);
        }
    }

    public function callback(Request $request)
    {
        // Get reference from query params
        $reference = $request->query('reference');

        // If reference is empty, try trxref (Paystack sometimes uses this)
        if (!$reference) {
            $reference = $request->query('trxref');
        }

        if (!$reference) {
            abort(400, 'Reference missing');
        }

        // Ensure reference is a string (not an object or array)
        if (is_object($reference)) {
            $reference = $reference->reference ?? $reference->id ?? (string) $reference;
        }

        if (is_array($reference)) {
            $reference = $reference['reference'] ?? $reference['id'] ?? (string) $reference;
        }

        // Cast to string to prevent [object Object]
        $referenceString = (string) $reference;

        // Validate it's not an empty string or [object Object]
        if (empty($referenceString) || $referenceString === '[object Object]') {
            Log::error('Invalid reference after conversion', [
                'original' => $reference,
                'converted' => $referenceString
            ]);
            return redirect(config('app.frontend_url') . '/dashboard/payments?error=invalid_reference');
        }

        // ✅ Call verify and get the response
        $response = $this->verify($request, $referenceString);

        // ✅ Check if verification was successful (status code 200)
        if ($response->getStatusCode() !== 200) {
            $responseData = $response->getData();
            Log::error('Verification failed for reference: ' . $referenceString, [
                'status_code' => $response->getStatusCode(),
                'response' => $responseData
            ]);
            return redirect(config('app.frontend_url') . '/dashboard/payments?error=verification_failed');
        }

        // ✅ Redirect to success page
        return redirect(
            config('app.frontend_url') .
                "/dashboard/payment/success/{$referenceString}?reference={$referenceString}&trxref={$referenceString}"
        );
    }
}
