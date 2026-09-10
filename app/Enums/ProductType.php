<?php

namespace App\Enums;

enum ProductType: string
{
    case Topup = 'topup';
    case Key = 'key';
    case Subscription = 'subscription';
    case Giftcard = 'giftcard';
}
