<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    protected $fillable = [
        'name',
        'description',
        'ip',
        'location',
        'monitorEnabled',
        'snmpCommunity',
        'model',
        'api_user',
        'api_password',
        'latitude',
        'longitude',
        'coverage_radius_km',
        'signal_strength',
        'max_clients',
        'current_clients',
        'device_type',
        'status'
    ];
}
