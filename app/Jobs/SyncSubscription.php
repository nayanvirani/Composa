<?php

namespace App\Jobs;

use App\Models\Shop;
use App\Services\BillingSync;
use App\Services\PlanEnforcer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** app_subscriptions/update: the merchant chose, changed or cancelled a plan on Shopify. */
class SyncSubscription implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $shopId, public array $payload) {}

    public function handle(BillingSync $billing, PlanEnforcer $enforcer): void
    {
        $shop = Shop::query()->find($this->shopId);
        if (! $shop) {
            return;
        }
        $billing->fromWebhook($shop, $this->payload);
        $enforcer->afterPlanChange($shop->refresh());
    }
}
