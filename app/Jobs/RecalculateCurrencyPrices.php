<?php

namespace App\Jobs;

use App\Services\PriceRecalculator;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecalculateCurrencyPrices implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $currencyId)
    {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return (string) $this->currencyId;
    }

    public function handle(PriceRecalculator $recalculator): void
    {
        $recalculator->forCurrency($this->currencyId);
    }
}
