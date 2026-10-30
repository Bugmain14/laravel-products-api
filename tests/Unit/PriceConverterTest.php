<?php

namespace Tests\Unit;

use App\Enums\PriceOrigin;
use App\Models\Currency;
use App\Models\Product;
use App\Services\PriceConverter;
use PHPUnit\Framework\TestCase;

class PriceConverterTest extends TestCase
{
    private PriceConverter $converter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->converter = new PriceConverter;
    }

    private function currency(int $id, string $rate): Currency
    {
        $currency = new Currency(['name' => "C{$id}", 'symbol' => '$', 'exchange_rate' => $rate]);
        $currency->id = $id;

        return $currency;
    }

    public function test_it_converts_between_currencies(): void
    {
        $usd = $this->currency(1, '1');
        $cop = $this->currency(2, '4000');
        $eur = $this->currency(3, '0.92');

        $this->assertSame('400000.00', $this->converter->convert('100', $usd, $cop));
        $this->assertSame('92.00', $this->converter->convert('100', $usd, $eur));
        $this->assertSame('23.00', $this->converter->convert('100000', $cop, $eur));
    }

    public function test_it_keeps_the_amount_for_the_same_currency(): void
    {
        $usd = $this->currency(1, '1');

        $this->assertSame('1200.50', $this->converter->convert('1200.5', $usd, $usd));
    }

    public function test_it_rounds_half_up_to_two_decimals(): void
    {
        $usd = $this->currency(1, '1');
        $half = $this->currency(2, '0.5');

        $this->assertSame('0.03', $this->converter->convert('0.05', $usd, $half));
        $this->assertSame('0.58', $this->converter->convert('1.15', $usd, $half));
        $this->assertSame('0.33', $this->converter->convert('1', $this->currency(3, '3'), $usd));
    }

    public function test_it_resolves_manual_and_calculated_prices(): void
    {
        $usd = $this->currency(1, '1');
        $cop = $this->currency(2, '4000');
        $product = new Product(['price' => '100']);
        $product->setRelation('currency', $usd);

        $manual = $this->converter->priceFor($product, $cop, 395000);
        $calculated = $this->converter->priceFor($product, $cop);

        $this->assertSame('395000.00', $manual->amount);
        $this->assertSame(PriceOrigin::Manual, $manual->origin);
        $this->assertSame('400000.00', $calculated->amount);
        $this->assertSame(PriceOrigin::Calculated, $calculated->origin);
        $this->assertSame(['price' => '400000.00', 'origin' => PriceOrigin::Calculated], $calculated->toAttributes());
    }

    public function test_it_is_exact_with_large_amounts_where_float_fails(): void
    {
        $from = $this->currency(1, '678.769387');
        $to = $this->currency(2, '396.179099');

        $float = number_format(round(8953434742.77 / 678.769387 * 396.179099, 2), 2, '.', '');

        $this->assertSame('5225874615.57', $float);
        $this->assertSame('5225874615.56', $this->converter->convert('8953434742.77', $from, $to));
    }

    public function test_it_converts_the_maximum_product_price_to_cop_without_precision_loss(): void
    {
        $usd = $this->currency(1, '1');
        $cop = $this->currency(2, '4123.456789');

        $this->assertSame('41234567889958.77', $this->converter->convert('9999999999.99', $usd, $cop));
    }
}
