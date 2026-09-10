<?php

namespace App\Models;

use App\Enums\KeyStatus;
use Illuminate\Database\Eloquent\Model;

class KeyPool extends Model
{
    protected $table = 'key_pool';

    protected $fillable = [
        'code',
        'status',
        'order_id',
        'issued_at',
    ];

    protected $casts = [
        'status' => KeyStatus::class,
        'issued_at' => 'datetime',
    ];
}
