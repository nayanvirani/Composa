<?php

namespace App\Http\Controllers\App;

use App\Models\Plan;
use App\Services\PlanLimits;
use App\Support\PlanCatalog;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Plan & usage. Plans are bought and changed only on Shopify's hosted plan page (Shopify App
 * Pricing); charges appear on the merchant's Shopify invoice.
 */
class BillingController extends Controller
{
    public function __invoke(): Response
    {
        $shop = $this->shop();
        $limits = PlanLimits::for($shop);
        $plans = Plan::query()->where('is_public', true)->orderBy('sort')->get()->reject(fn ($p) => $p->dev_only && ! $shop->is_dev)->values();

        return Inertia::render('Billing', [
            'plan' => $shop->plan ? ['handle' => $shop->plan->handle, 'name' => $shop->plan->name, 'price' => $shop->plan->priceLabel(), 'features' => $shop->plan->features] : null,
            'trialEndsAt' => $shop->trial_ends_at?->toIso8601String(),
            'meters' => $limits->meters(),
            'labels' => PlanCatalog::limitLabels(),
            'plans' => $plans->map(fn (Plan $p) => ['handle' => $p->handle, 'name' => $p->name, 'price' => $p->priceLabel(), 'limits' => $p->limits, 'features' => $p->features]),
            'impact' => $plans->mapWithKeys(fn (Plan $p) => [$p->handle => $this->impact($limits->usage(), $p)]),
            'pricingUrl' => $shop->pricingPageUrl(),
            'graceUntil' => $shop->setting('downgrade_grace_until'),
        ]);
    }

    /** What would no longer fit on another plan. */
    private function impact(array $usage, Plan $plan): array
    {
        $out = [];
        foreach (PlanCatalog::limitLabels() as $key => $label) {
            $limit = $plan->limit($key);
            if ($limit !== null && ($usage[$key] ?? 0) > $limit) {
                $out[] = ['ok' => false, 'text' => $label.': '.$usage[$key].' used, '.$plan->name.' allows '.$limit];
            }
        }

        return $out;
    }
}
