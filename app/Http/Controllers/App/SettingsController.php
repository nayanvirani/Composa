<?php

namespace App\Http\Controllers\App;

use App\Jobs\SyncStorefrontMetafields;
use App\Services\PlanLimits;
use App\Services\VariantMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(): Response
    {
        $shop = $this->shop();
        $limits = PlanLimits::for($shop);

        return Inertia::render('Settings', [
            'settings' => [
                'appearance' => $shop->setting('appearance', ['inherit_theme' => true, 'accent' => null, 'radius' => 'theme']),
                'cart' => $shop->setting('cart', ['line_title' => 'product_custom', 'summary_lines' => 6, 'flag_signature_failures' => true, 'after_add' => 'drawer']),
                'files' => $shop->setting('files', ['types' => config('composa.uploads.allowed'), 'max_mb' => config('composa.uploads.max_mb')]),
                'markets' => $shop->setting('markets', ['units' => 'imperial', 'ring_sizes' => 'US', 'rounding' => 'none']),
                'production' => $shop->setting('production', ['order_tags' => 'configured', 'workshop_email' => '', 'notify_usage' => true, 'pdf' => ['name' => $shop->name, 'footer' => '']]),
                'variant_presets' => $shop->setting('variant_presets', VariantMapper::PRESETS),
            ],
            'cart' => ['mode' => $shop->cart_mode, 'transform' => (bool) $shop->cart_transform_id, 'conflict' => (bool) $shop->setting('cart_transform_conflict')],
            'storage' => ['used_mb' => $limits->usage()['storage_mb'], 'limit_mb' => $limits->limit('storage_mb')],
            'features' => $shop->plan?->features ?? [],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'appearance' => 'array', 'cart' => 'array', 'files' => 'array', 'markets' => 'array', 'production' => 'array', 'variant_presets' => 'array',
            'files.max_mb' => 'integer|min:1|max:'.config('composa.uploads.max_mb'),
            'production.workshop_email' => 'nullable|email',
        ]);
        $shop = $this->shop();
        $settings = $shop->settings ?? [];
        foreach (['appearance', 'cart', 'files', 'markets', 'production', 'variant_presets'] as $key) {
            if (array_key_exists($key, $data)) {
                $settings[$key] = $data[$key];
            }
        }
        if (! $shop->plan?->feature('pdf_branding')) {
            unset($settings['production']['pdf']);
        }
        $shop->forceFill(['settings' => $settings])->save();
        SyncStorefrontMetafields::dispatch($shop->id);

        return response()->json(['ok' => true]);
    }
}
