<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Created = 'created';
    case Paid = 'paid';
    case Delivering = 'delivering';
    case Delivered = 'delivered';
    case PaymentFailed = 'payment_failed';
    case OutOfStock = 'out_of_stock';
    case DeliveryFailed = 'delivery_failed';

    public function isFinal(): bool
    {
        return in_array($this, [self::Delivered, self::PaymentFailed], true);
    }

    public function canStartDelivery(): bool
    {
        return in_array($this, [self::Paid, self::OutOfStock, self::DeliveryFailed], true);
    }
}
