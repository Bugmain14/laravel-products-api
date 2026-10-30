<?php

namespace App\Actions;

use App\Models\Currency;
use App\Models\Product;
use App\Services\PriceConverter;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreateProductWithPrices
{
    public function __construct(private readonly PriceConverter $converter)
    {
    }

    public function handle(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            $product = Product::create(Arr::except($data, 'prices'));

            $prices = $data['prices'] ?? [];
            $currencies = Currency::findMany(array_column($prices, 'currency_id'))->keyBy('id');

            foreach ($prices as $price) {
                $target = $currencies[$price['currency_id']];

                $product->prices()->create([
                    'currency_id' => $target->id,
                    'price' => $this->converter->priceFor($product, $target, $price['price'] ?? null),
                ]);
            }

            return $product->load(['currency', 'prices.currency']);
        });
    }
}
