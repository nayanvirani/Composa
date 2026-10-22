<?php

namespace App\Services;

use App\Jobs\SyncStorefrontMetafields;
use App\Models\Shop;
use App\Shopify\AdminApi;
use App\Shopify\TokenExchange;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Installs (or re-installs) Composa for a shop on its first authenticated request: exchanges the
 * session token for an offline token, reads the shop's details, picks the cart mode, registers the
 * Cart Transform, syncs the Shopify App Pricing subscription and publishes storefront metafields.
 */
class ShopInstaller
{
    private const SHOP_QUERY = <<<'GQL'
    query ComposaShop {
      shop {
        name email contactEmail currencyCode ianaTimezone myshopifyDomain
        currencyFormats { moneyFormat }
        plan { displayName partnerDevelopment shopifyPlus }
        shopOwnerName
      }
      shopLocales { locale primary published }
    }
    GQL;

    public function __construct(private readonly CartTransformSetup $cartTransform, private readonly BillingSync $billing) {}

    public function install(string $domain, string $sessionToken): Shop
    {
        $token = TokenExchange::offline($domain, $sessionToken);
        $shop = Shop::query()->firstOrNew(['domain' => $domain]);
        $reinstall = $shop->exists && $shop->status === 'uninstalled';
        $shop->forceFill([
            'access_token' => $token['access_token'],
            'scopes' => $token['scope'],
            'status' => 'active',
            'installed_at' => $shop->installed_at && ! $reinstall ? $shop->installed_at : now(),
            'uninstalled_at' => null,
            'purge_after' => null,
        ])->save();

        $this->refreshDetails($shop);
        $shop->ensureSigningSecret();

        try {
            $this->cartTransform->ensure($shop);
        } catch (Throwable $e) {
            Log::warning('Cart transform setup failed for '.$domain.': '.$e->getMessage());
        }
        try {
            $this->billing->sync($shop);
        } catch (Throwable $e) {
            Log::warning('Billing sync failed for '.$domain.': '.$e->getMessage());
        }
        SyncStorefrontMetafields::dispatch($shop->id);
        if (! $shop->wasRecentlyCreated && ! $reinstall) {
            return $shop->refresh();
        }
        app(LifecycleMail::class)->send($shop->refresh(), 'welcome', once: 'welcome:'.$shop->installed_at->toDateString());

        return $shop;
    }

    /** Re-reads name, currency, locales and Shopify plan (also used by the shop/update webhook). */
    public function refreshDetails(Shop $shop): void
    {
        $data = AdminApi::for($shop)->query(self::SHOP_QUERY);
        $s = $data['shop'] ?? [];
        $locales = collect($data['shopLocales'] ?? [])->where('published', true)->pluck('locale')->values()->all();
        $primary = collect($data['shopLocales'] ?? [])->firstWhere('primary', true)['locale'] ?? 'en';

        $shop->forceFill([
            'name' => $s['name'] ?? $shop->name,
            'email' => $s['contactEmail'] ?? $s['email'] ?? $shop->email,
            'owner_name' => $s['shopOwnerName'] ?? $shop->owner_name,
            'currency' => $s['currencyCode'] ?? $shop->currency,
            'money_format' => $s['currencyFormats']['moneyFormat'] ?? $shop->money_format,
            'timezone' => $s['ianaTimezone'] ?? $shop->timezone,
            'shopify_plan' => $s['plan']['displayName'] ?? null,
            'is_plus' => (bool) ($s['plan']['shopifyPlus'] ?? false),
            'is_dev' => (bool) ($s['plan']['partnerDevelopment'] ?? false),
            'primary_locale' => $primary,
            'locales' => $locales ?: [$primary],
        ]);
        // Keep the discount fallback after a cart transform conflict, and any mode staff chose.
        if (! $shop->setting('cart_transform_conflict') && ! $shop->setting('cart_mode_locked')) {
            $shop->cart_mode = CartTransformSetup::modeFor($shop);
        }
        $shop->save();
    }

    public function uninstalled(Shop $shop): void
    {
        $shop->forceFill([
            'status' => 'uninstalled',
            'access_token' => null,
            'cart_transform_id' => null,
            'uninstalled_at' => now(),
            'purge_after' => now()->addHours(config('composa.uninstall_purge_hours')),
        ])->save();
        app(LifecycleMail::class)->send($shop, 'uninstalled', once: 'uninstalled:'.now()->toDateString());
    }
}
