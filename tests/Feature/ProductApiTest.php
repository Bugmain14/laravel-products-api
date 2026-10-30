<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    private Currency $usd;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usd = Currency::create(['name' => 'USD', 'symbol' => '$', 'exchange_rate' => 1]);
    }

    private function authenticate(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
    }

    public function test_guests_cannot_access_products(): void
    {
        $this->getJson('/api/products')->assertUnauthorized();
    }

    public function test_it_lists_products_paginated(): void
    {
        $this->authenticate();
        Product::factory()->count(3)->create(['currency_id' => $this->usd->id]);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data' => [['id', 'name', 'price', 'currency']], 'links', 'meta']);
    }

    public function test_it_creates_a_product(): void
    {
        $this->authenticate();

        $payload = [
            'name' => 'Laptop',
            'description' => 'Business laptop',
            'price' => 1200.50,
            'currency_id' => $this->usd->id,
            'tax_cost' => 228.10,
            'manufacturing_cost' => 800,
        ];

        $this->postJson('/api/products', $payload)
            ->assertCreated()
            ->assertJsonPath('data.name', 'Laptop')
            ->assertJsonPath('data.price', '1200.50')
            ->assertJsonPath('data.currency.name', 'USD');

        $this->assertDatabaseHas('products', ['name' => 'Laptop']);
    }

    public function test_it_validates_required_fields(): void
    {
        $this->authenticate();

        $this->postJson('/api/products', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'price', 'currency_id', 'tax_cost', 'manufacturing_cost']);
    }

    public function test_it_rejects_negative_prices_and_unknown_currencies(): void
    {
        $this->authenticate();

        $this->postJson('/api/products', [
            'name' => 'Phone',
            'price' => -10,
            'currency_id' => 999,
            'tax_cost' => 0,
            'manufacturing_cost' => 0,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['price', 'currency_id']);
    }

    public function test_it_shows_a_product(): void
    {
        $this->authenticate();
        $product = Product::factory()->create(['currency_id' => $this->usd->id]);

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $product->id);
    }

    public function test_it_returns_404_for_missing_product(): void
    {
        $this->authenticate();

        $this->getJson('/api/products/999')
            ->assertNotFound()
            ->assertJson(['message' => 'Resource not found.']);
    }

    public function test_it_updates_a_product(): void
    {
        $this->authenticate();
        $product = Product::factory()->create(['currency_id' => $this->usd->id]);

        $this->putJson("/api/products/{$product->id}", ['name' => 'Updated name'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated name');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Updated name']);
    }

    public function test_it_deletes_a_product(): void
    {
        $this->authenticate();
        $product = Product::factory()->create(['currency_id' => $this->usd->id]);

        $this->deleteJson("/api/products/{$product->id}")->assertNoContent();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }
}
