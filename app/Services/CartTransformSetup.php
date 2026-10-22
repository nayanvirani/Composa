<?php

namespace App\Services;

use App\Models\Shop;
use App\Shopify\AdminApi;
use App\Support\Monitor;

/**
 * Registers Composa's Cart Transform Function for a shop and gives it its config: the per-shop
 * signing secret and the cart mode, in an app-owned metafield only the function can read.
 *
 * A store can run one cart transform at a time; if another app already owns it, the conflict is
 * recorded for super admin and shown to the merchant instead of silently failing at checkout.
 */
class CartTransformSetup
{
    public function ensure(Shop $shop): ?string
    {
        $api = AdminApi::for($shop);
        $existing = $api->query('query { cartTransforms(first: 25) { nodes { id functionId } } shopifyFunctions(first: 50) { nodes { id apiType app { apiKey } } } }');
        $ours = collect($existing['shopifyFunctions']['nodes'] ?? [])
            ->filter(fn ($f) => ($f['apiType'] ?? '') === 'cart_transform' && ($f['app']['apiKey'] ?? null) === config('composa.shopify.api_key'))
            ->pluck('id')->all();

        $id = null;
        foreach ($existing['cartTransforms']['nodes'] ?? [] as $node) {
            if (in_array($node['functionId'], $ours, true)) {
                $id = $node['id'];
            }
        }

        if (! $id) {
            try {
                $created = $api->mutate(
                    'mutation($handle: String!) { cartTransformCreate(functionHandle: $handle, blockOnFailure: false) { cartTransform { id } userErrors { field message } } }',
                    ['handle' => config('composa.shopify.cart_transform_function')],
                    'cartTransformCreate',
                );
                $id = $created['cartTransform']['id'] ?? null;
            } catch (\RuntimeException $e) {
                Monitor::record(Monitor::CART_TRANSFORM_CONFLICT, 'Could not register the cart transform: '.$e->getMessage(), $shop, 'warning');
                $shop->forceFill(['settings' => array_merge($shop->settings ?? [], ['cart_transform_conflict' => true])])->save();

                return null;
            }
        }

        $shop->forceFill(['cart_transform_id' => $id])->save();
        $this->writeConfig($shop);

        return $id;
    }

    /** Pushes the secret and mode to the function's metafield (call again when the mode changes). */
    public function writeConfig(Shop $shop): void
    {
        if (! $shop->cart_transform_id) {
            return;
        }
        AdminApi::for($shop)->mutate(
            'mutation($m: [MetafieldsSetInput!]!) { metafieldsSet(metafields: $m) { metafields { id } userErrors { field message } } }',
            ['m' => [[
                'ownerId' => $shop->cart_transform_id,
                'namespace' => '$app:composa',
                'key' => 'config',
                'type' => 'json',
                'value' => json_encode(['v' => 1, 'shop' => $shop->domain, 'secret' => $shop->ensureSigningSecret(), 'mode' => $shop->cart_mode]),
            ]]],
            'metafieldsSet',
        );
    }

    /** Update mode needs Shopify Plus (or a development store); every other plan uses Expand. */
    public static function modeFor(Shop $shop): string
    {
        if (config('composa.cart.force_discount_fallback')) {
            return 'discount';
        }

        return $shop->is_plus || $shop->is_dev ? 'update' : 'expand';
    }
}
