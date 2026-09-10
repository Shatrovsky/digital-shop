<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductStock extends Model
{
    protected $fillable = [
        'sku',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];
}
