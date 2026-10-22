<?php

namespace App\Jobs;

use App\Models\ConfiguredOrder;
use App\Services\ProductionPdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateProductionPdf implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $configuredOrderId) {}

    public function handle(ProductionPdf $pdf): void
    {
        if ($order = ConfiguredOrder::query()->find($this->configuredOrderId)) {
            $pdf->generate($order);
        }
    }
}
