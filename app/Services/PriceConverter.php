<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\Product;

class PriceConverter
{
    public function priceFor(Product $product, Currency $target, int|float|string|null $manual = null): string
    {
        if ($manual !== null) {
            return (string) $manual;
        }

        return $this->convert($product->price, $product->currency, $target);
    }

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
