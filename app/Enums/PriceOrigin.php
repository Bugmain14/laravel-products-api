<?php

namespace App\Enums;

enum PriceOrigin: string
{
    case Manual = 'manual';
    case Calculated = 'calculated';
}
