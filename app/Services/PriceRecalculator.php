<?php

namespace App\Services;

use App\Models\ProductPrice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class PriceRecalculator
{
    public function __construct(private readonly PriceConverter $converter)
    {
    }

    public function forCurrency(int $currencyId): void
    {
        $this->recalculate(
            ProductPrice::query()->where(function (Builder $query) use ($currencyId) {
                $query->where('currency_id', $currencyId)
                    ->orWhereHas('product', fn (Builder $query) => $query->withTrashed()->where('currency_id', $currencyId));
            })
        );
    }

    public function forProduct(int $productId): void
    {
        $this->recalculate(ProductPrice::query()->where('product_id', $productId));
    }

    private function recalculate(Builder $query): void
    {
        $query->calculated()
            ->with(['currency', 'product' => fn ($query) => $query->withTrashed()->with('currency')])
            ->chunkById(100, function (Collection $prices) {
                foreach ($prices as $price) {
                    $price->update([
                        'price' => $this->converter->convert($price->product->price, $price->product->currency, $price->currency),
                    ]);
                }
            });
    }
}
