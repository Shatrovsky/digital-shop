<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    protected $fillable = [
        'order_id',
        'code',
        'supplier',
        'request_id',
        'issued_at',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
    ];
}
