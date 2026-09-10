<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'product_id',
        'sku',
        'amount',
        'currency',
        'status',
        'payment_event_id',
        'paid_at',
        'delivered_at',
        'delivery_request_id',
        'current_supplier',
        'supplier_attempts',
        'delivery_lease_token',
        'delivery_lease_expires_at',
    ];

    protected $casts = [
        'status' => OrderStatus::class,
        'paid_at' => 'datetime',
        'delivered_at' => 'datetime',
        'delivery_lease_expires_at' => 'datetime',
        'supplier_attempts' => 'integer',
    ];

    public function delivery(): HasOne
    {
        return $this->hasOne(Delivery::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
