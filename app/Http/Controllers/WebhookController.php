<?php

namespace App\Http\Controllers;

use App\Jobs\HandleAppUninstalled;
use App\Jobs\ProcessGdprRequest;
use App\Jobs\ProcessOrderCreated;
use App\Jobs\RefreshShopDetails;
use App\Jobs\SyncProductChanges;
use App\Jobs\SyncSubscription;
use App\Models\GdprRequest;
use App\Models\Shop;
use App\Shopify\ShopDomain;
use App\Support\Monitor;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Every Shopify webhook arrives here (signature checked by VerifyShopifyWebhook). Work is queued
 * so Shopify gets a fast 200; duplicates are ignored by webhook id.
 */
class WebhookController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $topic = (string) $request->header('X-Shopify-Topic');
        $domain = ShopDomain::normalize($request->header('X-Shopify-Shop-Domain'));
        $webhookId = (string) $request->header('X-Shopify-Webhook-Id', '');
        $payload = $request->json()->all();

        if ($webhookId !== '' && ! Cache::add('webhook:'.$webhookId, 1, now()->addDay())) {
            return response('Duplicate', 200);
        }

        $shop = Shop::query()->where('domain', $domain)->first();

        try {
            match ($topic) {
                'app/uninstalled' => $shop && HandleAppUninstalled::dispatch($shop->id),
                'app/scopes_update' => $shop?->forceFill(['scopes' => implode(',', (array) ($payload['current'] ?? []))])->save(),
                'app_subscriptions/update' => $shop && SyncSubscription::dispatch($shop->id, $payload),
                'orders/create' => $shop && ProcessOrderCreated::dispatch($shop->id, $payload),
                'products/update', 'products/delete' => $shop && SyncProductChanges::dispatch($shop->id, $topic, $payload),
                'shop/update', 'themes/publish', 'locales/create', 'locales/update' => $shop && RefreshShopDetails::dispatch($shop->id),
                'customers/data_request', 'customers/redact', 'shop/redact' => $this->gdpr($topic, (string) $domain, $shop, $payload),
                default => null,
            };
        } catch (Throwable $e) {
            Monitor::record(Monitor::WEBHOOK_FAILED, $topic.': '.$e->getMessage(), $shop, 'warning', ['webhook_id' => $webhookId]);
            Cache::forget('webhook:'.$webhookId);

            return response('Error', 500);
        }

        return response('OK', 200);
    }

    private function gdpr(string $topic, string $domain, ?Shop $shop, array $payload): void
    {
        $request = GdprRequest::query()->create([
            'shop_id' => $shop?->id,
            'shop_domain' => $domain,
            'topic' => $topic,
            'payload' => $payload,
            'status' => 'pending',
            // Shopify expects data requests and redactions to be completed within 30 days.
            'due_at' => now()->addDays($topic === 'shop/redact' ? 2 : 30),
        ]);
        ProcessGdprRequest::dispatch($request->id);
    }
}
