<?php

use App\Models\PaymentAuthorization;

$authorization = $data['data']['authorization'] ?? null;

if ($authorization && $data['data']['channel'] === 'card') {
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
