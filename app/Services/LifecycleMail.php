<?php

namespace App\Services;

use App\Mail\TemplatedMail;
use App\Models\Shop;
use Illuminate\Support\Facades\Mail;

/**
 * Merchant lifecycle emails (welcome, trial ending, limits, downgrade, uninstall) from the
 * templates editable in super admin. Each email is sent at most once per marker, recorded in the
 * shop's settings, so daily checks never repeat themselves.
 */
class LifecycleMail
{
    public function send(Shop $shop, string $template, array $tokens = [], ?string $once = null): bool
    {
        if (! $shop->email || ! TemplatedMail::exists($template)) {
            return false;
        }
        $settings = $shop->settings ?? [];
        if ($once !== null && isset($settings['emails_sent'][$once])) {
            return false;
        }

        Mail::to($shop->email)->queue(new TemplatedMail($template, $tokens + [
            'shop_name' => $shop->name ?? $shop->domain,
            'owner_name' => $shop->owner_name ?: 'there',
            'plan_name' => $shop->plan?->name,
        ]));

        if ($once !== null) {
            $settings['emails_sent'][$once] = now()->toIso8601String();
            $shop->forceFill(['settings' => $settings])->save();
        }

        return true;
    }

    /** Daily: trial reminders and usage warnings for every active shop. */
    public function daily(): void
    {
        Shop::query()->where('status', 'active')->whereNotNull('email')->with('plan')->each(function (Shop $shop) {
            if ($shop->trial_ends_at && $shop->trial_ends_at->isFuture() && $shop->trial_ends_at->lte(now()->addDays(3))) {
                $this->send($shop, 'trial_ending', [
                    'days' => max(1, (int) ceil(now()->diffInDays($shop->trial_ends_at))),
                    'trial_ends_at' => $shop->trial_ends_at->toFormattedDayDateString(),
                ], 'trial_ending:'.$shop->trial_ends_at->toDateString());
            }

            $plan = $shop->plan?->handle ?? 'none';
            foreach (PlanLimits::for($shop)->meters() as $key => $meter) {
                if ($meter['limit'] === null || $meter['state'] === 'ok') {
                    continue;
                }
                $name = str($key)->replace('_', ' ')->replace('storage mb', 'upload storage')->toString();
                $this->send($shop, $meter['state'] === 'reached' ? 'limit_reached' : 'limit_warning', [
                    'limit_name' => $name, 'used' => $meter['used'], 'limit' => $meter['limit'], 'percent' => (int) round($meter['ratio'] * 100),
                ], 'limit:'.$plan.':'.$key.':'.$meter['state']);
            }
        });
    }
}
