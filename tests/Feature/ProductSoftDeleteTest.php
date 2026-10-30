<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductSoftDeleteTest extends TestCase
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
        $this->product = Product::factory()->create(['currency_id' => $this->usd->id, 'price' => 100]);
        $this->product->prices()->create(['currency_id' => $this->cop->id, 'price' => 400000]);
    }

    private function actingAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
    }

    private function actingAsViewer(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_deleting_a_product_soft_deletes_it_and_keeps_its_prices(): void
    {
        $this->actingAsAdmin();

        $this->deleteJson("/api/products/{$this->product->id}")->assertNoContent();

        $this->assertSoftDeleted('products', ['id' => $this->product->id]);
        $this->assertDatabaseHas('product_prices', ['product_id' => $this->product->id, 'currency_id' => $this->cop->id]);

        $this->getJson('/api/products')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/products/{$this->product->id}")->assertNotFound();
        $this->getJson("/api/products/{$this->product->id}/prices")->assertNotFound();
    }

    public function test_it_lists_only_trashed_products(): void
    {
        $this->actingAsAdmin();
        Product::factory()->create(['currency_id' => $this->usd->id]);
        $this->product->delete();

        $this->getJson('/api/products/trashed')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->product->id)
            ->assertJsonStructure(['data' => [['id', 'name', 'price', 'currency']], 'links', 'meta']);
    }

    public function test_it_restores_a_trashed_product_with_its_prices(): void
    {
        $this->actingAsAdmin();
        $this->product->delete();

        $this->postJson("/api/products/{$this->product->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.id', $this->product->id)
            ->assertJsonCount(1, 'data.prices')
            ->assertJsonPath('data.prices.0.currency.name', 'COP');

        $this->assertNotSoftDeleted('products', ['id' => $this->product->id]);
        $this->getJson('/api/products')->assertJsonCount(1, 'data');
    }

    public function test_restoring_an_active_product_returns_409(): void
    {
        $this->actingAsAdmin();

        $this->postJson("/api/products/{$this->product->id}/restore")
            ->assertStatus(409)
            ->assertJson(['message' => 'The product is not deleted.']);
    }

    public function test_it_force_deletes_a_trashed_product_and_its_prices(): void
    {
        $this->actingAsAdmin();
        $this->product->delete();

        $this->deleteJson("/api/products/{$this->product->id}/force")->assertNoContent();

        $this->assertDatabaseMissing('products', ['id' => $this->product->id]);
        $this->assertDatabaseMissing('product_prices', ['product_id' => $this->product->id]);
    }

    public function test_force_deleting_an_active_product_returns_409(): void
    {
        $this->actingAsAdmin();

        $this->deleteJson("/api/products/{$this->product->id}/force")
            ->assertStatus(409)
            ->assertJson(['message' => 'The product must be deleted before it can be permanently removed.']);

        $this->assertNotSoftDeleted('products', ['id' => $this->product->id]);
        $this->assertDatabaseHas('product_prices', ['product_id' => $this->product->id]);
    }

    public function test_viewer_cannot_list_trashed_products(): void
    {
        $this->actingAsViewer();

        $this->getJson('/api/products/trashed')
            ->assertForbidden()
            ->assertJson(['message' => 'This action is unauthorized.']);
    }

    public function test_viewer_cannot_restore_a_product(): void
    {
        $this->actingAsViewer();
        $this->product->delete();

        $this->postJson("/api/products/{$this->product->id}/restore")
            ->assertForbidden()
            ->assertJson(['message' => 'This action is unauthorized.']);

        $this->assertSoftDeleted('products', ['id' => $this->product->id]);
    }

    public function test_viewer_cannot_force_delete_a_product(): void
    {
        $this->actingAsViewer();
        $this->product->delete();

        $this->deleteJson("/api/products/{$this->product->id}/force")
            ->assertForbidden()
            ->assertJson(['message' => 'This action is unauthorized.']);

        $this->assertSoftDeleted('products', ['id' => $this->product->id]);
        $this->assertDatabaseHas('product_prices', ['product_id' => $this->product->id]);
    }
}
