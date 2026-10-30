<?php

namespace App\Observers;

use App\Jobs\RecalculateCurrencyPrices;
use App\Models\Currency;

class CurrencyObserver
{
    public function updated(Currency $currency): void
    {
        if ($currency->wasChanged('exchange_rate')) {
            RecalculateCurrencyPrices::dispatch($currency->id);
        }
    }
}
