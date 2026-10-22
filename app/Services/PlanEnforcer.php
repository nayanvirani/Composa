<?php

namespace App\Services;

use App\Jobs\SyncStorefrontMetafields;
use App\Models\Shop;
use Illuminate\Support\Carbon;

/**
 * After a downgrade, configurators over the new plan's limit get a grace period, then the most
 * recently published extras move back to draft. Nothing is deleted, and limits never block
 * checkout for configurations already in a cart.
 */
class PlanEnforcer
{
    public function afterPlanChange(Shop $shop): void
    {
        $limits = PlanLimits::for($shop);
        $over = $limits->usage()['configurators'] > ($limits->limit('configurators') ?? PHP_INT_MAX);
        $settings = $shop->settings ?? [];
        if ($over && empty($settings['downgrade_grace_until'])) {
            $settings['downgrade_grace_until'] = now()->addDays(config('composa.limits.downgrade_grace_days'))->toIso8601String();
        } elseif (! $over) {
            unset($settings['downgrade_grace_until']);
        }
        $shop->forceFill(['settings' => $settings])->save();
    }

    /** Daily: applies expired grace periods. */
    public function enforceExpiredGrace(): int
    {
        $moved = 0;
        Shop::query()->where('status', 'active')->whereNotNull('settings->downgrade_grace_until')->each(function (Shop $shop) use (&$moved) {
            $until = $shop->setting('downgrade_grace_until');
            if (! $until || Carbon::parse($until)->isFuture()) {
                return;
            }
            $limit = PlanLimits::for($shop)->limit('configurators') ?? PHP_INT_MAX;
            $published = $shop->configurators()->where('status', 'published')->orderByDesc('published_at')->get();
            foreach ($published->slice(0, max(0, $published->count() - $limit)) as $configurator) {
                $configurator->forceFill(['status' => 'draft'])->save();
                $moved++;
            }
            $settings = $shop->settings;
            unset($settings['downgrade_grace_until']);
            $shop->forceFill(['settings' => $settings])->save();
            SyncStorefrontMetafields::dispatch($shop->id);
        });

        return $moved;
    }
}
