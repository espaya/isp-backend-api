<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackService
{
    protected static function client()
    {
        return Http::withToken(config('services.paystack.secret_key'))
            ->acceptJson();
    }

    /**
     * Initialize a transaction (card or redirect)
     */
    public static function initialize(array $payload): array
    {
        $response = self::client()
            ->post(config('services.paystack.base_url') . '/transaction/initialize', $payload);

        return self::handleResponse($response, 'initialize');
    }

    /**
     * Verify a transaction
     */
    public static function verify(string $reference): array
    {
        $response = self::client()
            ->get(config('services.paystack.base_url') . "/transaction/verify/{$reference}");

        return self::handleResponse($response, 'verify');
    }

    /**
     * 🔥 Charge returning customer using saved authorization
     */
    public static function chargeAuthorization(
        string $email,
        string $authorizationCode,
        int $amount,
        string $reference
    ): array {
        $response = self::client()
            ->post(config('services.paystack.base_url') . '/transaction/charge_authorization', [
                'email' => $email,
                'authorization_code' => $authorizationCode,
                'amount' => $amount, // pesewas
                'reference' => $reference,
            ]);

        return self::handleResponse($response, 'charge_authorization');
    }

    /**
     * Centralized response handler
     */
    protected static function handleResponse($response, string $context): array
    {
        if (!$response->ok()) {
            Log::error("Paystack {$context} HTTP error", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \Exception("Paystack {$context} request failed");
        }

        $data = $response->json();

        if (!($data['status'] ?? false)) {
            Log::error("Paystack {$context} API error", $data);
            throw new \Exception($data['message'] ?? 'Paystack error');
        }

        return $data;
    }
}
