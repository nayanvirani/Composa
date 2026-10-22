<?php

namespace App\Http\Controllers\Proxy;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsEvent;
use App\Models\Configuration;
use App\Models\Shop;
use App\Models\Upload;
use App\Services\ConfigurationFactory;
use App\Services\PlanLimits;
use App\Services\PreviewCompositor;
use App\Services\ProductCatalog;
use App\Services\StorefrontPublisher;
use App\Support\Monitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Storefront endpoints behind the Shopify App Proxy (https://{shop}/apps/composa/*). The shop comes
 * only from Shopify's signed query string (VerifyAppProxy).
 */
class StorefrontController extends Controller
{
    private function shop(Request $request): Shop
    {
        return $request->attributes->get('shop');
    }

    /** Configurator payload (fallback when the theme can't read the metafield). */
    public function configurator(Request $request, int $id): JsonResponse
    {
        $c = $this->shop($request)->configurators()->with('publishedVersion')->where('status', 'published')->findOrFail($id);

        return response()->json(StorefrontPublisher::payload($c));
    }

    /** Server price, for a final check before add to cart. */
    public function price(Request $request, ConfigurationFactory $factory): JsonResponse
    {
        $data = $this->validateAnswers($request);
        $this->throttle($request, 'price', 120);
        $r = $factory->evaluate($this->shop($request), $data['configurator_id'], $data['answers'] ?? [], $data['quantity'] ?? 1, $data['product_id'] ?? null, $data['variant_id'] ?? null, $request->input('country'));

        return response()->json(['price' => $r['result']['price'], 'valid' => $r['result']['valid'], 'errors' => $r['result']['errors'], 'variant_id' => $r['result']['variant_id']]);
    }

    /** Add to cart: recalculate, store, sign. */
    public function createConfiguration(Request $request, ConfigurationFactory $factory): JsonResponse
    {
        $data = $this->validateAnswers($request);
        $this->throttle($request, 'cfg', 30);
        $shop = $this->shop($request);
        $out = $factory->create($shop, $data['configurator_id'], $data['answers'] ?? [], $data['quantity'] ?? 1, $data['product_id'] ?? null, $data['variant_id'] ?? null, $request->input('country'));

        AnalyticsEvent::query()->create(['shop_id' => $shop->id, 'configurator_id' => $data['configurator_id'], 'session_id' => $request->input('session'), 'type' => 'add_to_cart', 'value' => $out['price_cents'], 'occurred_at' => now()]);

        return response()->json($out);
    }

    /** Presigned PUT straight to the private bucket (files never pass through the app server). */
    public function presignUpload(Request $request): JsonResponse
    {
        $shop = $this->shop($request);
        $limits = PlanLimits::for($shop);
        if (! $limits->feature('uploads')) {
            return response()->json(['message' => 'File uploads are not available.'], 403);
        }
        $maxMb = min((int) $shop->setting('files.max_mb', config('composa.uploads.max_mb')), config('composa.uploads.max_mb'));
        $types = $shop->setting('files.types', config('composa.uploads.allowed'));
        $data = $request->validate(['name' => 'required|string|max:200', 'mime' => 'required|string|max:128', 'size' => 'required|integer|min:1|max:'.($maxMb * 1048576), 'field' => 'required|string|max:64']);
        $ext = strtolower(pathinfo($data['name'], PATHINFO_EXTENSION));
        if (! in_array($ext, $types, true)) {
            return response()->json(['message' => 'This file type is not allowed.'], 422);
        }
        if (! $limits->allows('storage_mb', $data['size'] / 1048576)) {
            Monitor::record('storage_limit', 'Upload refused: storage limit reached', $shop, 'info');

            return response()->json(['message' => 'Uploads are temporarily unavailable for this store.'], 403);
        }
        $this->throttle($request, 'upload', 20);

        $id = 'upl_'.Str::lower(Str::random(18));
        $path = sprintf('shops/%d/uploads/%s/%s.%s', $shop->id, now()->format('Y/m'), $id, $ext);
        $disk = Storage::disk(config('composa.uploads.disk'));
        ['url' => $url, 'headers' => $headers] = $disk->temporaryUploadUrl($path, now()->addMinutes(config('composa.uploads.put_ttl_minutes')), ['ContentType' => $data['mime']]);

        Upload::query()->create(['id' => $id, 'shop_id' => $shop->id, 'field_key' => $data['field'], 'path' => $path, 'original_name' => mb_substr($data['name'], 0, 200), 'mime' => $data['mime'], 'size' => $data['size'], 'status' => 'pending']);

        return response()->json(['id' => $id, 'url' => $url, 'headers' => $headers]);
    }

    public function completeUpload(Request $request, string $id): JsonResponse
    {
        $upload = $this->shop($request)->uploads()->findOrFail($id);
        $disk = Storage::disk(config('composa.uploads.disk'));
        if (! $disk->exists($upload->path)) {
            return response()->json(['message' => 'Upload not received.'], 422);
        }
        $upload->forceFill(['status' => 'uploaded', 'size' => $disk->size($upload->path)])->save();

        return response()->json(['id' => $upload->id, 'name' => $upload->original_name]);
    }

    /** Analytics beacon: opens, steps reached, choices and load time. */
    public function events(Request $request): Response
    {
        $data = $request->validate(['configurator_id' => 'required|integer', 'session' => 'nullable|string|max:64', 'events' => 'required|array|max:50', 'events.*.type' => 'required|in:open,step,choice,load_ms', 'events.*.step' => 'nullable|string|max:64', 'events.*.value' => 'nullable|numeric', 'events.*.meta' => 'nullable|array']);
        $shop = $this->shop($request);
        $this->throttle($request, 'events', 240);
        if (! $shop->configurators()->whereKey($data['configurator_id'])->exists()) {
            return response()->noContent();
        }
        $now = now();
        $rows = array_map(fn ($e) => [
            'shop_id' => $shop->id, 'configurator_id' => $data['configurator_id'], 'session_id' => $data['session'] ?? null,
            'type' => $e['type'], 'step_key' => $e['step'] ?? null, 'value' => isset($e['value']) ? (int) $e['value'] : null,
            'meta' => isset($e['meta']) ? json_encode(array_slice($e['meta'], 0, 10)) : null, 'occurred_at' => $now,
        ], $data['events']);
        AnalyticsEvent::query()->insert($rows);

        foreach ($data['events'] as $e) {
            if ($e['type'] === 'load_ms' && ($e['value'] ?? 0) > config('composa.storefront.slow_load_ms')) {
                Monitor::record(Monitor::SLOW_LOAD, 'Configurator interactive after more than '.config('composa.storefront.slow_load_ms').' ms', $shop, 'warning', ['ms' => (int) $e['value']]);
            }
        }

        return response()->noContent();
    }

    /**
     * Dedicated configurator page (/apps/composa/configure/{handle}): returned as Liquid so Shopify
     * renders it inside the store's theme layout — shareable and indexable.
     */
    public function page(Request $request, string $handle, ProductCatalog $catalog): Response
    {
        $shop = $this->shop($request);
        $c = $shop->configurators()->with('publishedVersion')->where('status', 'published')->where('handle', $handle)->firstOrFail();
        $product = $c->base_product_id ? $catalog->product($shop, $c->base_product_id) : null;

        return response()->view('proxy.configure-page', [
            'configurator' => $c,
            'payload' => StorefrontPublisher::payload($c),
            'productHandle' => $product['handle'] ?? null,
        ])->header('Content-Type', 'application/liquid');
    }

    /** Configured preview image for the cart line and the order (composited and cached). */
    public function image(Request $request, string $configId, PreviewCompositor $compositor): Response
    {
        $configuration = Configuration::query()->where('shop_id', $this->shop($request)->id)->findOrFail($configId);
        $png = $compositor->png($configuration);

        return response($png, 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=31536000, immutable']);
    }

    private function validateAnswers(Request $request): array
    {
        return $request->validate([
            'configurator_id' => 'required|integer',
            'answers' => 'array',
            'quantity' => 'integer|min:1|max:999',
            'product_id' => 'nullable|string|max:120',
            'variant_id' => 'nullable|string|max:120',
        ]);
    }

    private function throttle(Request $request, string $bucket, int $perMinute): void
    {
        $key = 'proxy:'.$bucket.':'.$this->shop($request)->id.':'.$request->ip();
        abort_if(RateLimiter::tooManyAttempts($key, $perMinute), 429, 'Too many requests');
        RateLimiter::hit($key, 60);
    }
}
