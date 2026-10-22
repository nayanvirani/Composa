<?php

namespace App\Http\Middleware;

use App\Models\Announcement;
use App\Models\Impersonation;
use App\Models\Shop;
use App\Services\PlanLimits;
use App\Services\ThemeStatus;
use App\Support\FeatureFlags;
use Illuminate\Http\Request;
use Inertia\Middleware;

/** Shared props for every merchant app page. */
class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app.shell';

    public function share(Request $request): array
    {
        /** @var Shop|null $shop */
        $shop = $request->attributes->get('shop');
        $impersonation = $request->attributes->get('impersonation');

        return [
            ...parent::share($request),
            'app' => [
                'apiKey' => config('composa.shopify.api_key'),
                'name' => config('app.name'),
                'proxyPath' => '/'.config('composa.shopify.proxy_prefix').'/'.config('composa.shopify.proxy_subpath'),
            ],
            'shop' => $shop ? fn () => [
                'id' => $shop->id,
                'domain' => $shop->domain,
                'name' => $shop->name,
                'currency' => $shop->currency,
                'locale' => $shop->primary_locale,
                'locales' => $shop->locales ?? [$shop->primary_locale],
                'cartMode' => $shop->cart_mode,
                'isPlus' => $shop->is_plus,
                'isDev' => $shop->is_dev,
                'plan' => $shop->plan ? ['handle' => $shop->plan->handle, 'name' => $shop->plan->name, 'price' => $shop->plan->priceLabel(), 'features' => $shop->plan->features] : null,
                'subscription' => $shop->subscription_status,
                'trialEndsAt' => $shop->trial_ends_at?->toIso8601String(),
                'onboarding' => $shop->onboarding ?? [],
                'theme' => $shop->setting('theme'),
                'cartTransformConflict' => (bool) $shop->setting('cart_transform_conflict'),
                'pricingUrl' => $shop->pricingPageUrl(),
                'embedLink' => ThemeStatus::embedLink($shop),
                'blockLink' => ThemeStatus::blockLink($shop),
                'graceUntil' => $shop->setting('downgrade_grace_until'),
            ] : null,
            'usage' => $shop ? fn () => PlanLimits::for($shop)->meters() : null,
            'flags' => $shop ? fn () => FeatureFlags::forShop($shop) : [],
            'announcement' => $shop ? fn () => $this->announcement($shop) : null,
            'impersonation' => $impersonation instanceof Impersonation ? ['mode' => $impersonation->mode, 'admin' => $impersonation->user?->name, 'expiresAt' => $impersonation->expires_at->toIso8601String()] : null,
            'flash' => fn () => ['success' => $request->session()->get('success'), 'error' => $request->session()->get('error')],
        ];
    }

    private function announcement(Shop $shop): ?array
    {
        $a = Announcement::query()->where('scope', 'app')->where('active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->latest()->get()
            ->first(fn (Announcement $a) => empty($a->audience) || in_array($shop->plan?->handle, $a->audience, true));

        return $a ? ['message' => $a->message, 'linkLabel' => $a->link_label, 'linkUrl' => $a->link_url] : null;
    }
}
