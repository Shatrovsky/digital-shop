<?php

namespace App\Enums;

enum KeyStatus: string
{
    case Available = 'available';
    case Issued = 'issued';
    case Reserved = 'reserved';
}
