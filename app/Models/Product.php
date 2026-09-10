<?php

namespace App\Models;

use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'sku',
        'name',
        'type',
        'price',
        'currency',
        'image',
        'is_active',
    ];

    protected $casts = [
        'type' => ProductType::class,
        'price' => 'integer',
        'is_active' => 'boolean',
    ];
}
