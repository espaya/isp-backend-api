<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $fillable = [
        'user_id',
        'package_id',
        'status',
        'starts_at',
        'expires_at',
        'auto_renew',
        'is_renewable',
        'renewed_from_subscription_id',
        'payment_id',
        'mac_address',
        'ip_address',
        'mikrotik_device_id', //
        'hotspot_password'
    ];


    /**
     * The user who owns this subscription
     * **/
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 
     * The package associated with this subscription
     * **/
    public function package(): BelongsTo
    {
        return $this->belongsTo(Packages::class);
    }

    /**
     * The Mikrotik device associated with this subscription
     * **/
    public function mikrotikDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'mikrotik_device_id');
    }

    /**
     * If this subscription is a renewal of another subscription
     * */
    public function renewedFrom(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'renewed_from_subscription_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function scopeExpired($query)
    {
        return $query->whereRaw("expires_at <= NOW()");
    }
}
