<?php

namespace App\Jobs;

use App\Models\AnalyticsEvent;
use App\Models\Configuration;
use App\Models\ConfiguredOrder;
use App\Models\Shop;
use App\Services\ConfigurationSigner;
use App\Support\Monitor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;

/**
 * orders/create: links each order line carrying _config_id to its configuration, checks the price
 * actually charged against the signed price, then queues the production PDF and order tags.
 * Lines expanded by the Cart Transform keep the same _config_id, so they are summed together.
 */
class ProcessOrderCreated implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public int $shopId, public array $order) {}

    public function backoff(): array
    {
        return [10, 60, 300, 1800];
    }

    public function handle(): void
    {
        $shop = Shop::query()->find($this->shopId);
        if (! $shop) {
            return;
        }

        $groups = [];
        foreach ($this->order['line_items'] ?? [] as $line) {
            $props = collect($line['properties'] ?? [])->mapWithKeys(fn ($p) => [(string) ($p['name'] ?? '') => (string) ($p['value'] ?? '')]);
            $configId = $props->get('_config_id');
            if (! $configId) {
                continue;
            }
            $groups[$configId][] = ['line' => $line, 'props' => $props];
        }
        if (! $groups) {
            return;
        }

        $tags = ['composa'];
        $customer = trim(Arr::get($this->order, 'customer.first_name', '').' '.Arr::get($this->order, 'customer.last_name', '')) ?: null;
        $orderId = (string) ($this->order['admin_graphql_api_id'] ?? $this->order['id']);

        foreach ($groups as $configId => $lines) {
            $configuration = Configuration::query()->where('shop_id', $shop->id)->find($configId);
            $first = $lines[0]['line'];
            $sig = (string) $lines[0]['props']->get('_sig', '');

            $check = 'ok';
            $paidCents = 0;
            foreach ($lines as $l) {
                $unit = (float) Arr::get($l['line'], 'price_set.shop_money.amount', $l['line']['price'] ?? 0);
                $discount = (float) Arr::get($l['line'], 'total_discount_set.shop_money.amount', 0);
                $paidCents += (int) round($unit * 100) * (int) $l['line']['quantity'] - (int) round($discount * 100);
            }
            if (! $configuration) {
                $check = 'unsigned';
            } else {
                $message = ConfigurationSigner::message($shop->domain, $configuration->id, (int) $configuration->price_cents, (int) $configuration->base_price_cents, $configuration->currency, $configuration->result['price']['components'] ?? []);
                $valid = $sig !== '' && hash_equals($configuration->signature, strtolower($sig)) && ConfigurationSigner::verify((string) $shop->signing_secret, $message, $sig);
                $expected = (int) $configuration->price_cents * (int) $first['quantity'];
                if (! $valid) {
                    $check = 'unsigned';
                } elseif (abs($paidCents - $expected) > max(2, (int) $first['quantity'])) {
                    $check = 'mismatch';
                }
            }
            if ($check !== 'ok') {
                $tags[] = 'composa-price-check';
                Monitor::record($check === 'mismatch' ? Monitor::PRICE_MISMATCH : Monitor::SIGNATURE_FAILED, ($check === 'mismatch' ? 'Charged price differs from signed price' : 'Configured line without a valid signature').' on '.($this->order['name'] ?? $orderId), $shop, 'critical', ['config_id' => $configId, 'paid_cents' => $paidCents]);
            }

            $record = ConfiguredOrder::query()->updateOrCreate(
                ['shop_id' => $shop->id, 'order_id' => $orderId, 'line_item_id' => (string) ($first['admin_graphql_api_id'] ?? $first['id'])],
                [
                    'configuration_id' => $configuration?->id,
                    'configurator_id' => $configuration?->configurator_id,
                    'order_name' => $this->order['name'] ?? null,
                    'customer_name' => $customer,
                    'quantity' => (int) $first['quantity'],
                    'line_total_cents' => $paidCents,
                    'currency' => $this->order['currency'] ?? $shop->currency,
                    'price_check' => $check,
                    'placed_at' => $this->order['created_at'] ?? now(),
                ],
            );

            if ($configuration) {
                $configuration->forceFill(['status' => 'ordered', 'expires_at' => null])->save();
                $configuration->uploads()->update(['status' => 'ordered']);
                AnalyticsEvent::query()->create([
                    'shop_id' => $shop->id, 'configurator_id' => $configuration->configurator_id, 'type' => 'order',
                    'value' => $paidCents, 'meta' => ['order' => $orderId], 'occurred_at' => now(),
                ]);
                $tags[] = 'composa-'.($configuration->configurator?->handle ?? 'configured');
            }
            if ($shop->plan?->feature('pdf')) {
                GenerateProductionPdf::dispatch($record->id);
            }
        }

        $extra = array_filter(array_map('trim', explode(',', (string) $shop->setting('production.order_tags', ''))));
        TagOrder::dispatch($shop->id, $orderId, array_values(array_unique([...$tags, ...$extra])));
    }
}
