<?php

namespace App\Models;

use App\Enums\PriceOrigin;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPrice extends Model
{
    protected $fillable = ['product_id', 'currency_id', 'price', 'origin'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'origin' => PriceOrigin::class,
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    #[Scope]
    protected function calculated(Builder $query): void
    {
        $query->where('origin', PriceOrigin::Calculated);
    }
}
