<?php

namespace App\Services;

use App\Models\Currency;

class PriceConverter
{
    public function convert(string $amount, Currency $from, Currency $to): string
    {
        if ($from->is($to)) {
            return number_format((float) $amount, 2, '.', '');
        }

        $inReference = (float) $amount / (float) $from->exchange_rate;
        $converted = $inReference * (float) $to->exchange_rate;

        return number_format(round($converted, 2), 2, '.', '');
    }
}
