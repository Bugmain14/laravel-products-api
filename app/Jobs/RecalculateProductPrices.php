<?php

namespace App\Jobs;

use App\Services\PriceRecalculator;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecalculateProductPrices implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $productId)
    {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return (string) $this->productId;
    }

    public function handle(PriceRecalculator $recalculator): void
    {
        $recalculator->forProduct($this->productId);
    }
}
