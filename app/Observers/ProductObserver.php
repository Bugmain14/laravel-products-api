<?php

namespace App\Observers;

use App\Jobs\RecalculateProductPrices;
use App\Models\Product;

class ProductObserver
{
    public function updated(Product $product): void
    {
        if ($product->wasChanged('currency_id')) {
            $product->prices()->where('currency_id', $product->currency_id)->calculated()->delete();
        }

        if ($product->wasChanged(['price', 'currency_id'])) {
            RecalculateProductPrices::dispatch($product->id);
        }
    }
}
