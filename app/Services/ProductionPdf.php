<?php

namespace App\Services;

use App\Models\ConfiguredOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/** Builds the production spec PDF for a configured order line and stores it in the private bucket. */
class ProductionPdf
{
    public function generate(ConfiguredOrder $order): string
    {
        $order->loadMissing(['shop.plan', 'configuration.version', 'configurator']);
        $shop = $order->shop;
        $configuration = $order->configuration;

        $pdf = Pdf::loadView('pdf.production-spec', [
            'order' => $order,
            'shop' => $shop,
            'configuration' => $configuration,
            'rows' => SpecSheet::rows($configuration?->version?->definition ?? [], $configuration?->answers ?? []),
            'branded' => (bool) $shop->plan?->feature('pdf_branding'),
            'brand' => $shop->setting('production.pdf', []),
        ])->setPaper('a4');

        $path = sprintf('shops/%d/pdf/%s-%s.pdf', $shop->id, ConfigurationSigner::numericId($order->order_id), ConfigurationSigner::numericId($order->line_item_id));
        Storage::disk(config('composa.uploads.disk'))->put($path, $pdf->output());
        $order->forceFill(['spec_pdf_path' => $path])->save();

        return $path;
    }
}
