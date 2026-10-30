<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductPriceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'price' => $this->price,
            'currency' => new CurrencyResource($this->whenLoaded('currency')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
