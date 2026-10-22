<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Shop;
use App\Models\Subscription;
use App\Shopify\AdminApi;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Keeps each shop's plan in step with its Shopify App Pricing subscription. Merchants choose and
 * change plans on Shopify's hosted plan page; Composa only reads the result (no custom payment
 * gateway, no coupons, discounts or credits).
 */
class BillingSync
{
    private const QUERY = <<<'GQL'
    query ComposaSubscriptions {
      currentAppInstallation {
        activeSubscriptions {
          id name status trialDays currentPeriodEnd createdAt test
          lineItems { plan { pricingDetails { __typename ... on AppRecurringPricing { price { amount currencyCode } interval } } } }
        }
      }
    }
    GQL;

    public function sync(Shop $shop): void
    {
        $subs = AdminApi::for($shop)->query(self::QUERY)['currentAppInstallation']['activeSubscriptions'] ?? [];
        $active = collect($subs)->firstWhere('status', 'ACTIVE') ?? ($subs[0] ?? null);
        if ($active) {
            $this->apply($shop, [
                'admin_graphql_api_id' => $active['id'],
                'name' => $active['name'],
                'status' => $active['status'],
                'trial_days' => $active['trialDays'] ?? 0,
                'created_at' => $active['createdAt'] ?? null,
                'current_period_end' => $active['currentPeriodEnd'] ?? null,
                'price' => $active['lineItems'][0]['plan']['pricingDetails']['price']['amount'] ?? null,
                'currency' => $active['lineItems'][0]['plan']['pricingDetails']['price']['currencyCode'] ?? 'USD',
            ]);

            return;
        }
        $this->applyFallback($shop);
    }

    /** app_subscriptions/update webhook payload (the app_subscription object). */
    public function fromWebhook(Shop $shop, array $payload): void
    {
        $this->apply($shop, $payload['app_subscription'] ?? $payload);
    }

    private function apply(Shop $shop, array $sub): void
    {
        $status = strtoupper((string) ($sub['status'] ?? ''));
        $plan = $this->matchPlan((string) ($sub['name'] ?? ''), $sub['price'] ?? null);
        $created = isset($sub['created_at']) ? Carbon::parse($sub['created_at']) : now();
        $trialDays = (int) ($sub['trial_days'] ?? 0);

        Subscription::query()->updateOrCreate(['shopify_id' => (string) ($sub['admin_graphql_api_id'] ?? $sub['id'] ?? Str::uuid())], [
            'shop_id' => $shop->id,
            'plan_handle' => $plan?->handle,
            'name' => $sub['name'] ?? null,
            'status' => $status,
            'price_cents' => isset($sub['price']) ? (int) round((float) $sub['price'] * 100) : (int) ($plan?->price_cents ?? 0),
            'currency' => $sub['currency'] ?? 'USD',
            'trial_ends_at' => $trialDays ? $created->copy()->addDays($trialDays) : null,
            'current_period_end' => isset($sub['current_period_end']) ? Carbon::parse($sub['current_period_end']) : null,
            'payload' => $sub,
        ]);

        if (in_array($status, ['ACTIVE', 'ACCEPTED'], true)) {
            $shop->forceFill(['plan_id' => $plan?->id ?? $shop->plan_id, 'subscription_status' => 'active', 'trial_ends_at' => $trialDays ? $created->copy()->addDays($trialDays) : null])->save();
        } elseif (in_array($status, ['CANCELLED', 'DECLINED', 'EXPIRED', 'FROZEN'], true)) {
            $shop->forceFill(['subscription_status' => strtolower($status)])->save();
            $this->applyFallback($shop);
        }
    }

    /** Development stores get the free dev plan; other shops without a subscription have no plan. */
    private function applyFallback(Shop $shop): void
    {
        $plan = $shop->is_dev ? Plan::query()->where('handle', 'free-dev')->first() : null;
        $shop->forceFill(['plan_id' => $plan?->id, 'subscription_status' => $plan ? 'dev' : ($shop->subscription_status ?: 'none')])->save();
    }

    /** Shopify sends the plan's display name; match it to a Composa plan by name or handle. */
    public function matchPlan(string $name, mixed $price = null): ?Plan
    {
        $plans = Plan::query()->get();
        $key = Str::slug($name);

        return $plans->first(fn (Plan $p) => Str::slug($p->name) === $key || $p->handle === $key || Str::contains($key, $p->handle))
            ?? ($price !== null ? $plans->firstWhere('price_cents', (int) round((float) $price * 100)) : null);
    }
}
