<?php

namespace App\ValueObjects;

use App\Enums\PriceOrigin;

final readonly class ResolvedPrice
{
    public function __construct(
        public string $amount,
        public PriceOrigin $origin,
    ) {
    }

    public function toAttributes(): array
    {
        return [
            'price' => $this->amount,
            'origin' => $this->origin,
        ];
    }
}
