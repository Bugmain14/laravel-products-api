<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductAuthorizationTest extends TestCase
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
            'name' => 'Original',
            'price' => 100,
        ]);

        Sanctum::actingAs(User::factory()->create());
    }

    public function test_viewer_can_list_products(): void
    {
        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_viewer_can_view_a_product(): void
    {
        $this->getJson("/api/products/{$this->product->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $this->product->id);
    }

    public function test_viewer_can_list_the_prices_of_a_product(): void
    {
        $this->product->prices()->create(['currency_id' => $this->cop->id, 'price' => 400000]);

        $this->getJson("/api/products/{$this->product->id}/prices")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_viewer_cannot_create_a_product(): void
    {
        $this->postJson('/api/products', [
            'name' => 'Laptop',
            'price' => 1200,
            'currency_id' => $this->usd->id,
            'tax_cost' => 228,
            'manufacturing_cost' => 800,
        ])->assertForbidden()
            ->assertJson(['message' => 'This action is unauthorized.']);

        $this->assertDatabaseMissing('products', ['name' => 'Laptop']);
    }

    public function test_viewer_is_forbidden_before_validation_runs(): void
    {
        $this->postJson('/api/products', [])
            ->assertForbidden()
            ->assertJsonMissingPath('errors');
    }

    public function test_viewer_cannot_update_a_product(): void
    {
        $this->putJson("/api/products/{$this->product->id}", ['name' => 'Hacked'])
            ->assertForbidden()
            ->assertJson(['message' => 'This action is unauthorized.']);

        $this->assertDatabaseHas('products', ['id' => $this->product->id, 'name' => 'Original']);
    }

    public function test_viewer_cannot_delete_a_product(): void
    {
        $this->deleteJson("/api/products/{$this->product->id}")
            ->assertForbidden()
            ->assertJson(['message' => 'This action is unauthorized.']);

        $this->assertNotSoftDeleted('products', ['id' => $this->product->id]);
    }

    public function test_viewer_cannot_register_a_price(): void
    {
        $this->postJson("/api/products/{$this->product->id}/prices", [
            'currency_id' => $this->cop->id,
            'price' => 395000,
        ])->assertForbidden()
            ->assertJson(['message' => 'This action is unauthorized.']);

        $this->assertDatabaseMissing('product_prices', ['product_id' => $this->product->id]);
    }
}
