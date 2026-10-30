<?php

namespace App\Models;

use App\Observers\CurrencyObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([CurrencyObserver::class])]
class Currency extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'symbol', 'exchange_rate'];

    protected function casts(): array
    {
        return [
            'exchange_rate' => 'decimal:6',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function productPrices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function isInUse(): bool
    {
        return $this->products()->withTrashed()->exists() || $this->productPrices()->exists();
    }
}
