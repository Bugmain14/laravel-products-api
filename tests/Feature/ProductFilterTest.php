<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductFilterTest extends TestCase
{
    use RefreshDatabase;

    private Product $laptopPro;

    private Product $deskLamp;

    protected function setUp(): void
    {
        parent::setUp();

        $usd = Currency::create(['name' => 'USD', 'symbol' => '$', 'exchange_rate' => 1]);
        $eur = Currency::create(['name' => 'EUR', 'symbol' => '€', 'exchange_rate' => 0.92]);

        $this->laptopPro = Product::factory()->create([
            'name' => 'Laptop Pro',
            'description' => 'Business laptop',
            'price' => 1200,
            'currency_id' => $usd->id,
        ]);
        Product::factory()->create([
            'name' => 'Wireless Mouse',
            'description' => 'Ergonomic LAPTOP accessory',
            'price' => 25,
            'currency_id' => $usd->id,
        ]);
        $this->deskLamp = Product::factory()->create([
            'name' => 'Desk Lamp',
            'description' => null,
            'price' => 40,
            'currency_id' => $usd->id,
        ]);
        Product::factory()->create([
            'name' => 'Laptop Basic',
            'description' => 'Entry level',
            'price' => 500,
            'currency_id' => $eur->id,
        ]);

        Sanctum::actingAs(User::factory()->admin()->create());
    }

    private function names(TestResponse $response): array
    {
        return collect($response->json('data'))->pluck('name')->all();
    }

    public function test_it_searches_by_name_case_insensitively(): void
    {
        $response = $this->getJson('/api/products?search=PRO')->assertOk();

        $this->assertSame(['Laptop Pro'], $this->names($response));
    }

    public function test_it_searches_by_description_case_insensitively(): void
    {
        $response = $this->getJson('/api/products?search=ERGONOMIC')->assertOk();

        $this->assertSame(['Wireless Mouse'], $this->names($response));
    }

    public function test_it_filters_by_currency_code_case_insensitively(): void
    {
        $response = $this->getJson('/api/products?currency=eur')->assertOk();

        $this->assertSame(['Laptop Basic'], $this->names($response));
    }

    public function test_it_filters_by_an_inclusive_price_range(): void
    {
        $response = $this->getJson('/api/products?min_price=40&max_price=500&sort=price')->assertOk();

        $this->assertSame(['Desk Lamp', 'Laptop Basic'], $this->names($response));
    }

    public function test_it_sorts_ascending(): void
    {
        $response = $this->getJson('/api/products?sort=price')->assertOk();

        $this->assertSame(['Wireless Mouse', 'Desk Lamp', 'Laptop Basic', 'Laptop Pro'], $this->names($response));
    }

    public function test_it_sorts_descending(): void
    {
        $response = $this->getJson('/api/products?sort=-name')->assertOk();

        $this->assertSame(['Wireless Mouse', 'Laptop Pro', 'Laptop Basic', 'Desk Lamp'], $this->names($response));
    }

    public function test_it_combines_filters(): void
    {
        $response = $this->getJson('/api/products?search=laptop&currency=usd&min_price=10&sort=-price')->assertOk();

        $this->assertSame(['Laptop Pro', 'Wireless Mouse'], $this->names($response));
    }

    public function test_it_rejects_an_invalid_sort(): void
    {
        $this->getJson('/api/products?sort=stock')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort']);

        $this->getJson('/api/products?sort=--price')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort']);
    }

    public function test_it_rejects_a_min_price_greater_than_max_price(): void
    {
        $this->getJson('/api/products?min_price=500&max_price=100')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['max_price']);
    }

    public function test_it_accepts_max_price_without_min_price(): void
    {
        $response = $this->getJson('/api/products?max_price=40&sort=price')->assertOk();

        $this->assertSame(['Wireless Mouse', 'Desk Lamp'], $this->names($response));
    }

    public function test_it_rejects_per_page_out_of_range(): void
    {
        $this->getJson('/api/products?per_page=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);

        $this->getJson('/api/products?per_page=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_pagination_links_keep_the_filters(): void
    {
        $response = $this->getJson('/api/products?search=laptop&sort=price&per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 3);

        $next = $response->json('links.next');

        $this->assertStringContainsString('search=laptop', $next);
        $this->assertStringContainsString('sort=price', $next);
        $this->assertStringContainsString('per_page=1', $next);
        $this->assertStringContainsString('page=2', $next);
    }

    public function test_filters_apply_to_trashed_products(): void
    {
        $this->laptopPro->delete();
        $this->deskLamp->delete();

        $response = $this->getJson('/api/products/trashed?search=LAPTOP&sort=-price')->assertOk();

        $this->assertSame(['Laptop Pro'], $this->names($response));

        $this->getJson('/api/products/trashed?sort=invalid')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort']);
    }
}
