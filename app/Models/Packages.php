<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Packages extends Model
{
    protected $fillable = [
        'name',
        'speed',
        'price',
        'validity',
        'dataLimit',
        'isActive',
        'description',
        'devices'
    ];
}
