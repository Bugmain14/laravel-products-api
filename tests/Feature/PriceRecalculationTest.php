<?php

namespace Tests\Feature;

use App\Enums\PriceOrigin;
use App\Jobs\RecalculateCurrencyPrices;
use App\Jobs\RecalculateProductPrices;
use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PriceRecalculationTest extends TestCase
{
    use RefreshDatabase;

    private Currency $usd;

    private Currency $cop;

    private Currency $eur;

    private Currency $mxn;

    private Product $laptop;

    private Product $coffee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usd = Currency::create(['name' => 'USD', 'symbol' => '$', 'exchange_rate' => 1]);
        $this->cop = Currency::create(['name' => 'COP', 'symbol' => '$', 'exchange_rate' => 4000]);
        $this->eur = Currency::create(['name' => 'EUR', 'symbol' => '€', 'exchange_rate' => 0.92]);
        $this->mxn = Currency::create(['name' => 'MXN', 'symbol' => '$', 'exchange_rate' => 18.5]);

        $this->laptop = Product::factory()->create(['name' => 'Laptop', 'currency_id' => $this->usd->id, 'price' => 100]);
        $this->laptop->prices()->createMany([
            ['currency_id' => $this->cop->id, 'price' => 400000, 'origin' => PriceOrigin::Calculated],
            ['currency_id' => $this->eur->id, 'price' => 95, 'origin' => PriceOrigin::Manual],
            ['currency_id' => $this->mxn->id, 'price' => 1850, 'origin' => PriceOrigin::Calculated],
        ]);

        $this->coffee = Product::factory()->create(['name' => 'Coffee', 'currency_id' => $this->cop->id, 'price' => 400000]);
        $this->coffee->prices()->create(['currency_id' => $this->usd->id, 'price' => 100, 'origin' => PriceOrigin::Calculated]);

        Sanctum::actingAs(User::factory()->admin()->create());
    }

    private function priceOf(Product $product, Currency $currency): ?ProductPrice
    {
        return ProductPrice::where('product_id', $product->id)->where('currency_id', $currency->id)->first();
    }

    public function test_changing_the_exchange_rate_dispatches_the_currency_job(): void
    {
        Queue::fake();

        $this->putJson("/api/currencies/{$this->cop->id}", ['exchange_rate' => 4100])->assertOk();

        Queue::assertPushed(RecalculateCurrencyPrices::class, fn ($job) => $job->currencyId === $this->cop->id);
        Queue::assertNotPushed(RecalculateProductPrices::class);
    }

    public function test_other_currency_changes_do_not_dispatch_the_job(): void
    {
        Queue::fake();

        $this->putJson("/api/currencies/{$this->cop->id}", ['symbol' => 'COL$'])->assertOk();
        $this->putJson("/api/currencies/{$this->cop->id}", ['exchange_rate' => '4000.000000'])->assertOk();

        Queue::assertNothingPushed();
    }

    public function test_changing_the_price_or_base_currency_dispatches_the_product_job(): void
    {
        Queue::fake();

        $this->putJson("/api/products/{$this->laptop->id}", ['price' => 150])->assertOk();
        $this->putJson("/api/products/{$this->coffee->id}", ['currency_id' => $this->eur->id])->assertOk();

        Queue::assertPushed(RecalculateProductPrices::class, fn ($job) => $job->productId === $this->laptop->id);
        Queue::assertPushed(RecalculateProductPrices::class, fn ($job) => $job->productId === $this->coffee->id);
    }

    public function test_other_product_changes_do_not_dispatch_the_job(): void
    {
        Queue::fake();

        $this->putJson("/api/products/{$this->laptop->id}", [
            'name' => 'Laptop 2',
            'description' => 'Updated',
            'tax_cost' => 10,
            'price' => 100,
            'currency_id' => $this->usd->id,
        ])->assertOk();

        Queue::assertNothingPushed();
    }

    public function test_the_currency_job_recalculates_calculated_prices_only(): void
    {
        Queue::fake();
        $this->coffee->delete();
        $this->cop->update(['exchange_rate' => 4100]);

        $this->app->call([new RecalculateCurrencyPrices($this->cop->id), 'handle']);

        $this->assertSame('410000.00', $this->priceOf($this->laptop, $this->cop)->price);
        $this->assertSame('97.56', $this->priceOf($this->coffee, $this->usd)->price);
        $this->assertSame('95.00', $this->priceOf($this->laptop, $this->eur)->price);
        $this->assertSame('1850.00', $this->priceOf($this->laptop, $this->mxn)->price);

        $this->app->call([new RecalculateCurrencyPrices($this->cop->id), 'handle']);

        $this->assertSame('410000.00', $this->priceOf($this->laptop, $this->cop)->price);
        $this->assertSame('97.56', $this->priceOf($this->coffee, $this->usd)->price);
    }

    public function test_the_product_job_recalculates_calculated_prices_only(): void
    {
        Queue::fake();
        $this->laptop->update(['price' => 200]);

        $this->app->call([new RecalculateProductPrices($this->laptop->id), 'handle']);

        $this->assertSame('800000.00', $this->priceOf($this->laptop, $this->cop)->price);
        $this->assertSame('3700.00', $this->priceOf($this->laptop, $this->mxn)->price);
        $this->assertSame('95.00', $this->priceOf($this->laptop, $this->eur)->price);
        $this->assertSame('100.00', $this->priceOf($this->coffee, $this->usd)->price);
    }

    public function test_updating_the_exchange_rate_recalculates_prices_end_to_end(): void
    {
        $this->putJson("/api/currencies/{$this->cop->id}", ['exchange_rate' => 4100])->assertOk();

        $this->getJson("/api/products/{$this->laptop->id}/prices")
            ->assertOk()
            ->assertJsonPath('data.0.currency.name', 'COP')
            ->assertJsonPath('data.0.price', '410000.00')
            ->assertJsonPath('data.0.origin', 'calculated')
            ->assertJsonPath('data.1.price', '95.00')
            ->assertJsonPath('data.1.origin', 'manual');
    }

    public function test_switching_the_base_to_a_currency_with_a_calculated_price_removes_that_price(): void
    {
        $this->putJson("/api/products/{$this->laptop->id}", [
            'currency_id' => $this->cop->id,
            'price' => 800000,
        ])->assertOk()
            ->assertJsonPath('data.currency.name', 'COP');

        $this->assertNull($this->priceOf($this->laptop, $this->cop));
        $this->assertSame('3700.00', $this->priceOf($this->laptop, $this->mxn)->price);
        $this->assertSame('95.00', $this->priceOf($this->laptop, $this->eur)->price);
    }

    public function test_switching_the_base_to_a_currency_with_a_manual_price_is_rejected(): void
    {
        Queue::fake();

        $this->putJson("/api/products/{$this->laptop->id}", [
            'currency_id' => $this->eur->id,
            'price' => 90,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'currency_id' => 'This product has a manual price in that currency. Delete that price before using it as the base currency.',
            ]);

        $this->assertDatabaseHas('products', ['id' => $this->laptop->id, 'currency_id' => $this->usd->id, 'price' => 100]);
        $this->assertSame('95.00', $this->priceOf($this->laptop, $this->eur)->price);
        $this->assertDatabaseCount('product_prices', 4);
        Queue::assertNothingPushed();
    }

    public function test_creation_endpoints_store_the_price_origin(): void
    {
        $response = $this->postJson('/api/products', [
            'name' => 'Phone',
            'price' => 100,
            'currency_id' => $this->usd->id,
            'tax_cost' => 0,
            'manufacturing_cost' => 0,
            'prices' => [
                ['currency_id' => $this->cop->id, 'price' => 395000],
                ['currency_id' => $this->eur->id],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.prices.0.origin', 'manual')
            ->assertJsonPath('data.prices.1.origin', 'calculated');

        $productId = $response->json('data.id');

        $this->postJson("/api/products/{$productId}/prices", ['currency_id' => $this->mxn->id])
            ->assertCreated()
            ->assertJsonPath('data.price', '1850.00')
            ->assertJsonPath('data.origin', 'calculated');

        $this->postJson("/api/products/{$this->coffee->id}/prices", ['currency_id' => $this->eur->id, 'price' => 90])
            ->assertCreated()
            ->assertJsonPath('data.origin', 'manual');
    }
}
