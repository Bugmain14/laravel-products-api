<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Product;
use App\Models\User;
use App\Services\PriceConverter;
use App\ValueObjects\ResolvedPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class ProductCreateWithPricesTest extends TestCase
{
    use RefreshDatabase;

    private Currency $usd;

    private Currency $cop;

    private Currency $eur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usd = Currency::create(['name' => 'USD', 'symbol' => '$', 'exchange_rate' => 1]);
        $this->cop = Currency::create(['name' => 'COP', 'symbol' => '$', 'exchange_rate' => 4000]);
        $this->eur = Currency::create(['name' => 'EUR', 'symbol' => '€', 'exchange_rate' => 0.92]);

        Sanctum::actingAs(User::factory()->admin()->create());
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Laptop',
            'description' => 'Business laptop',
            'price' => 100,
            'currency_id' => $this->usd->id,
            'tax_cost' => 19,
            'manufacturing_cost' => 60,
        ], $overrides);
    }

    public function test_it_creates_a_product_with_manual_and_calculated_prices(): void
    {
        $this->postJson('/api/products', $this->payload([
            'prices' => [
                ['currency_id' => $this->cop->id, 'price' => 395000],
                ['currency_id' => $this->eur->id],
            ],
        ]))->assertCreated()
            ->assertJsonPath('data.name', 'Laptop')
            ->assertJsonPath('data.currency.name', 'USD')
            ->assertJsonCount(2, 'data.prices')
            ->assertJsonPath('data.prices.0.currency.name', 'COP')
            ->assertJsonPath('data.prices.0.price', '395000.00')
            ->assertJsonPath('data.prices.1.currency.name', 'EUR')
            ->assertJsonPath('data.prices.1.price', '92.00');

        $product = Product::where('name', 'Laptop')->firstOrFail();

        $this->assertDatabaseCount('product_prices', 2);
        $this->assertDatabaseHas('product_prices', ['product_id' => $product->id, 'currency_id' => $this->cop->id, 'price' => 395000]);
        $this->assertDatabaseHas('product_prices', ['product_id' => $product->id, 'currency_id' => $this->eur->id, 'price' => 92]);
    }

    public function test_it_creates_a_product_without_prices(): void
    {
        $this->postJson('/api/products', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Laptop')
            ->assertJsonPath('data.currency.name', 'USD')
            ->assertJsonCount(0, 'data.prices');

        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('product_prices', 0);
    }

    public function test_it_rejects_a_repeated_currency_in_prices(): void
    {
        $this->postJson('/api/products', $this->payload([
            'prices' => [
                ['currency_id' => $this->cop->id, 'price' => 395000],
                ['currency_id' => $this->cop->id],
            ],
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['prices.1.currency_id' => 'Each price must use a different currency.']);

        $this->assertDatabaseCount('products', 0);
    }

    public function test_it_rejects_a_price_in_the_base_currency(): void
    {
        $this->postJson('/api/products', $this->payload([
            'prices' => [
                ['currency_id' => $this->usd->id, 'price' => 100],
            ],
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['prices.0.currency_id' => "The price currency must be different from the product's base currency."]);

        $this->assertDatabaseCount('products', 0);
    }

    public function test_it_rejects_invalid_price_entries(): void
    {
        $this->postJson('/api/products', $this->payload([
            'prices' => [
                ['currency_id' => 999],
                ['currency_id' => $this->cop->id, 'price' => -1],
                ['currency_id' => $this->eur->id, 'price' => 1.234],
            ],
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['prices.0.currency_id', 'prices.1.price', 'prices.2.price']);

        $this->assertDatabaseCount('products', 0);
    }

    public function test_it_rolls_back_the_product_and_prices_when_a_price_fails(): void
    {
        $this->app->instance(PriceConverter::class, new class extends PriceConverter
        {
            private int $calls = 0;

            public function priceFor(Product $product, Currency $target, int|float|string|null $manual = null): ResolvedPrice
            {
                if (++$this->calls === 2) {
                    throw new RuntimeException('Simulated price failure.');
                }

                return parent::priceFor($product, $target, $manual);
            }
        });

        $this->withoutExceptionHandling();

        $this->assertThrows(
            fn () => $this->postJson('/api/products', $this->payload([
                'prices' => [
                    ['currency_id' => $this->cop->id, 'price' => 395000],
                    ['currency_id' => $this->eur->id],
                ],
            ])),
            RuntimeException::class,
            'Simulated price failure.'
        );

        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_prices', 0);
    }
}
