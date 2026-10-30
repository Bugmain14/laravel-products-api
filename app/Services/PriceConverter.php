<?php

namespace App\Services;

use App\Enums\PriceOrigin;
use App\Models\Currency;
use App\Models\Product;
use App\ValueObjects\ResolvedPrice;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class PriceConverter
{
    public function priceFor(Product $product, Currency $target, int|float|string|null $manual = null): ResolvedPrice
    {
        if ($manual !== null) {
            return new ResolvedPrice($this->toMoney(BigDecimal::of((string) $manual)), PriceOrigin::Manual);
        }

        return new ResolvedPrice($this->convert($product->price, $product->currency, $target), PriceOrigin::Calculated);
    }

    public function convert(string $amount, Currency $from, Currency $to): string
    {
        $amount = BigDecimal::of($amount);

        if ($from->is($to)) {
            return $this->toMoney($amount);
        }

        return (string) $amount
            ->multipliedBy($to->exchange_rate)
            ->dividedBy($from->exchange_rate, 2, RoundingMode::HALF_UP);
    }

    private function toMoney(BigDecimal $amount): string
    {
        return (string) $amount->toScale(2, RoundingMode::HALF_UP);
    }
}
