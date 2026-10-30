<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CurrencyApiTest extends TestCase
{
    use RefreshDatabase;

    private Currency $usd;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usd = Currency::create(['name' => 'USD', 'symbol' => '$', 'exchange_rate' => 1]);
    }

    private function actingAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
    }

    private function actingAsViewer(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_it_lists_currencies_ordered_by_name(): void
    {
        $this->actingAsViewer();
        Currency::create(['name' => 'EUR', 'symbol' => '€', 'exchange_rate' => 0.92]);
        Currency::create(['name' => 'COP', 'symbol' => '$', 'exchange_rate' => 4000]);

        $this->getJson('/api/currencies')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.name', 'COP')
            ->assertJsonPath('data.1.name', 'EUR')
            ->assertJsonPath('data.2.name', 'USD');
    }

    public function test_it_shows_a_currency(): void
    {
        $this->actingAsViewer();

        $this->getJson("/api/currencies/{$this->usd->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $this->usd->id)
            ->assertJsonPath('data.name', 'USD')
            ->assertJsonPath('data.exchange_rate', '1.000000');
    }

    public function test_admin_creates_a_currency(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/currencies', [
            'name' => 'COP',
            'symbol' => '$',
            'exchange_rate' => 4000.5,
        ])->assertCreated()
            ->assertJsonPath('data.name', 'COP')
            ->assertJsonPath('data.symbol', '$')
            ->assertJsonPath('data.exchange_rate', '4000.500000');

        $this->assertDatabaseHas('currencies', ['name' => 'COP']);
    }

    public function test_it_normalizes_the_name_to_uppercase(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/currencies', [
            'name' => ' mxn ',
            'symbol' => '$',
            'exchange_rate' => 18.5,
        ])->assertCreated()
            ->assertJsonPath('data.name', 'MXN');

        $this->assertDatabaseHas('currencies', ['name' => 'MXN']);
    }

    public function test_it_rejects_a_duplicate_name(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/currencies', [
            'name' => 'usd',
            'symbol' => '$',
            'exchange_rate' => 1,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->assertDatabaseCount('currencies', 1);
    }

    public function test_it_rejects_a_non_positive_exchange_rate(): void
    {
        $this->actingAsAdmin();

        foreach ([0, -1] as $rate) {
            $this->postJson('/api/currencies', [
                'name' => 'EUR',
                'symbol' => '€',
                'exchange_rate' => $rate,
            ])->assertUnprocessable()
                ->assertJsonValidationErrors(['exchange_rate']);
        }

        $this->assertDatabaseMissing('currencies', ['name' => 'EUR']);
    }

    public function test_admin_updates_a_currency(): void
    {
        $this->actingAsAdmin();
        $cop = Currency::create(['name' => 'COP', 'symbol' => '$', 'exchange_rate' => 4000]);

        $this->putJson("/api/currencies/{$cop->id}", ['exchange_rate' => 4100])
            ->assertOk()
            ->assertJsonPath('data.name', 'COP')
            ->assertJsonPath('data.exchange_rate', '4100.000000');

        $this->assertDatabaseHas('currencies', ['id' => $cop->id, 'exchange_rate' => 4100]);
    }

    public function test_updating_with_its_own_name_does_not_fail_uniqueness(): void
    {
        $this->actingAsAdmin();

        $this->putJson("/api/currencies/{$this->usd->id}", ['name' => 'usd', 'symbol' => 'US$'])
            ->assertOk()
            ->assertJsonPath('data.name', 'USD')
            ->assertJsonPath('data.symbol', 'US$');
    }

    public function test_admin_deletes_an_unused_currency(): void
    {
        $this->actingAsAdmin();
        $eur = Currency::create(['name' => 'EUR', 'symbol' => '€', 'exchange_rate' => 0.92]);

        $this->deleteJson("/api/currencies/{$eur->id}")->assertNoContent();

        $this->assertDatabaseMissing('currencies', ['id' => $eur->id]);
    }

    public function test_it_returns_409_when_deleting_a_currency_used_by_a_product(): void
    {
        $this->actingAsAdmin();
        Product::factory()->create(['currency_id' => $this->usd->id]);

        $this->deleteJson("/api/currencies/{$this->usd->id}")
            ->assertStatus(409)
            ->assertJson(['message' => 'The currency is in use by products or product prices and cannot be deleted.']);

        $this->assertDatabaseHas('currencies', ['id' => $this->usd->id]);
    }

    public function test_it_returns_409_when_deleting_a_currency_used_by_a_product_price(): void
    {
        $this->actingAsAdmin();
        $cop = Currency::create(['name' => 'COP', 'symbol' => '$', 'exchange_rate' => 4000]);
        $product = Product::factory()->create(['currency_id' => $this->usd->id]);
        $product->prices()->create(['currency_id' => $cop->id, 'price' => 400000]);

        $this->deleteJson("/api/currencies/{$cop->id}")
            ->assertStatus(409)
            ->assertJson(['message' => 'The currency is in use by products or product prices and cannot be deleted.']);

        $this->assertDatabaseHas('currencies', ['id' => $cop->id]);
    }

    public function test_viewer_cannot_create_a_currency(): void
    {
        $this->actingAsViewer();

        $this->postJson('/api/currencies', [
            'name' => 'EUR',
            'symbol' => '€',
            'exchange_rate' => 0.92,
        ])->assertForbidden()
            ->assertJson(['message' => 'This action is unauthorized.']);

        $this->assertDatabaseMissing('currencies', ['name' => 'EUR']);
    }

    public function test_viewer_cannot_update_a_currency(): void
    {
        $this->actingAsViewer();

        $this->putJson("/api/currencies/{$this->usd->id}", ['exchange_rate' => 2])
            ->assertForbidden()
            ->assertJson(['message' => 'This action is unauthorized.']);

        $this->assertDatabaseHas('currencies', ['id' => $this->usd->id, 'exchange_rate' => 1]);
    }

    public function test_viewer_cannot_delete_a_currency(): void
    {
        $this->actingAsViewer();

        $this->deleteJson("/api/currencies/{$this->usd->id}")
            ->assertForbidden()
            ->assertJson(['message' => 'This action is unauthorized.']);

        $this->assertDatabaseHas('currencies', ['id' => $this->usd->id]);
    }
}
