<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    public const SORTABLE = ['name', 'price', 'created_at'];

    protected $fillable = [
        'name',
        'description',
        'price',
        'currency_id',
        'tax_cost',
        'manufacturing_cost',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'tax_cost' => 'decimal:2',
            'manufacturing_cost' => 'decimal:2',
        ];
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    #[Scope]
    protected function filter(Builder $query, array $filters, string $defaultSort = 'id'): void
    {
        $query->search($filters['search'] ?? null)
            ->ofCurrency($filters['currency'] ?? null)
            ->priceBetween($filters['min_price'] ?? null, $filters['max_price'] ?? null)
            ->sortBy($filters['sort'] ?? null, $defaultSort);
    }

    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        if (blank($term)) {
            return;
        }

        $query->where(function (Builder $query) use ($term) {
            $query->whereLike('name', "%{$term}%")
                ->orWhereLike('description', "%{$term}%");
        });
    }

    #[Scope]
    protected function ofCurrency(Builder $query, ?string $code): void
    {
        if (blank($code)) {
            return;
        }

        $query->whereHas('currency', fn (Builder $query) => $query->where('name', Str::upper($code)));
    }

    #[Scope]
    protected function priceBetween(Builder $query, int|float|string|null $min, int|float|string|null $max): void
    {
        $query->when(! blank($min), fn (Builder $query) => $query->where('price', '>=', $min))
            ->when(! blank($max), fn (Builder $query) => $query->where('price', '<=', $max));
    }

    #[Scope]
    protected function sortBy(Builder $query, ?string $sort, string $default = 'id'): void
    {
        $column = ltrim((string) $sort, '-');

        if (! in_array($column, self::SORTABLE, true)) {
            $query->latest($default);

            return;
        }

        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';

        $query->orderBy($column, $direction)->orderBy('id');
    }
}
