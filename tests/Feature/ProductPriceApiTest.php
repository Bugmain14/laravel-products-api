<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductPriceApiTest extends TestCase
{
    use RefreshDatabase;

    private Currency $usd;

    private Currency $cop;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usd = Currency::create(['name' => 'USD', 'symbol' => '$', 'exchange_rate' => 1]);
        $this->cop = Currency::create(['name' => 'COP', 'symbol' => '$', 'exchange_rate' => 4000]);
        $this->product = Product::factory()->create([
            'currency_id' => $this->usd->id,
            'price' => 100,
        ]);

        Sanctum::actingAs(User::factory()->admin()->create());
    }

    public function test_it_creates_a_price_with_a_manual_amount(): void
    {
        $this->postJson("/api/products/{$this->product->id}/prices", [
            'currency_id' => $this->cop->id,
            'price' => 395000,
        ])->assertCreated()
            ->assertJsonPath('data.price', '395000.00')
            ->assertJsonPath('data.currency.name', 'COP');
    }

    public function test_it_calculates_the_price_from_the_exchange_rate_when_omitted(): void
    {
        $this->postJson("/api/products/{$this->product->id}/prices", [
            'currency_id' => $this->cop->id,
        ])->assertCreated()
            ->assertJsonPath('data.price', '400000.00');
    }

    public function test_it_rejects_a_duplicate_currency_for_the_same_product(): void
    {
        $this->product->prices()->create(['currency_id' => $this->cop->id, 'price' => 400000]);

        $this->postJson("/api/products/{$this->product->id}/prices", [
            'currency_id' => $this->cop->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['currency_id']);
    }

    public function test_it_rejects_a_price_in_the_base_currency(): void
    {
        $this->postJson("/api/products/{$this->product->id}/prices", [
            'currency_id' => $this->usd->id,
            'price' => 100,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['currency_id' => "The price currency must be different from the product's base currency."]);
    }

    public function test_it_converts_large_amounts_to_cop_beyond_the_product_price_range(): void
    {
        $product = Product::factory()->create(['currency_id' => $this->usd->id, 'price' => '9999999999.99']);

        $this->postJson("/api/products/{$product->id}/prices", ['currency_id' => $this->cop->id])
            ->assertCreated()
            ->assertJsonPath('data.price', '39999999999960.00')
            ->assertJsonPath('data.origin', 'calculated');

        $this->assertDatabaseHas('product_prices', ['product_id' => $product->id, 'price' => '39999999999960.00']);
    }

    public function test_manual_prices_accept_the_wider_range_of_product_prices(): void
    {
        $this->postJson("/api/products/{$this->product->id}/prices", [
            'currency_id' => $this->cop->id,
            'price' => '39999999999960.00',
        ])->assertCreated()
            ->assertJsonPath('data.price', '39999999999960.00')
            ->assertJsonPath('data.origin', 'manual');

        $other = Product::factory()->create(['currency_id' => $this->usd->id]);

        $this->postJson("/api/products/{$other->id}/prices", [
            'currency_id' => $this->cop->id,
            'price' => '10000000000000000.00',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['price']);
    }

    public function test_it_lists_the_prices_of_a_product(): void
    {
        $this->product->prices()->create(['currency_id' => $this->cop->id, 'price' => 400000]);

        $this->getJson("/api/products/{$this->product->id}/prices")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.currency.name', 'COP');
    }

    public function test_soft_deleting_a_product_keeps_its_prices(): void
    {
        $this->product->prices()->create(['currency_id' => $this->cop->id, 'price' => 400000]);

        $this->deleteJson("/api/products/{$this->product->id}")->assertNoContent();

        $this->assertDatabaseHas('product_prices', ['product_id' => $this->product->id, 'currency_id' => $this->cop->id]);
    }
}
