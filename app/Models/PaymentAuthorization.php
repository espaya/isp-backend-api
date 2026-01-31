<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentAuthorization extends Model
{
    protected $fillable = [
        'user_id',
        'authorization_code',
        'card_type',
        'last4',
        'exp_month',
        'exp_year',
        'bank'
    ];
}
