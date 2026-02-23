<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    protected $fillable = [
        'user_id',
        'amount',
        'status', // e.g., pending, completed, failed
        'method', // e.g., card, mobile money
        'reference', // unique payment reference
        'paid_at', // timestamp when payment was completed
    ];

    /**
     * Get the subscriptions associated with this payment
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    // public function package(): HasMany
    // {
    //     return $this->hasMany(Packages::class);
    // }

    /**
     * Get the user who made the payment
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
