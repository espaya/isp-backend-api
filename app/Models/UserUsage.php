<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserUsage extends Model
{
    protected $fillable = [
        'user_id',
        'bytes_used',
        'usage_date',
        'subscription_id',
        'gb_used',
        'data_limit_gb',
        'percentage_used'
    ];
}
