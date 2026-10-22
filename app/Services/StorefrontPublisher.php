<?php

namespace App\Services;

use App\Models\Configurator;
use App\Models\Shop;
use App\Shopify\AdminApi;

/**
 * Caches what the storefront needs in shop metafields (namespace "composa") so the theme app
 * embed renders the Customize button and the configurator without a network round trip:
 *
 *   composa.assignments   [{t: product|collection|tag|vendor, id, c: configurator key, p: priority}]
 *   composa.settings      proxy path, cart mode, appearance and storefront strings
 *   composa.cfg_{id}      one published configurator: definition, display settings, meta
 *
 * Prices are always recalculated and signed by the server; nothing here is trusted at checkout.
 */
class StorefrontPublisher
{
    private const SET = 'mutation($m: [MetafieldsSetInput!]!) { metafieldsSet(metafields: $m) { metafields { id } userErrors { field message } } }';

    public function publish(Shop $shop): void
    {
        $api = AdminApi::for($shop);
        $ownerId = $api->query('query { shop { id } }')['shop']['id'];
        $ns = config('composa.shopify.metafield_namespace');

        $published = $shop->configurators()->where('status', 'published')->whereNotNull('published_version_id')->with(['publishedVersion', 'assignments'])->get();

        $metafields = [
            $this->json($ownerId, $ns, 'assignments', $this->assignments($published)),
            $this->json($ownerId, $ns, 'settings', $this->settings($shop)),
        ];
        foreach ($published as $configurator) {
            $metafields[] = $this->json($ownerId, $ns, 'cfg_'.$configurator->id, self::payload($configurator));
        }
        foreach (array_chunk($metafields, 25) as $chunk) {
            $api->mutate(self::SET, ['m' => $chunk], 'metafieldsSet');
        }

        $this->removeStale($api, $ownerId, $ns, $published->pluck('id')->all(), $shop);
    }

    /** What the storefront bundle receives for one configurator (also served by the App Proxy). */
    public static function payload(Configurator $c): array
    {
        $definition = $c->publishedVersion?->definition ?? [];

        return [
            'id' => $c->id,
            'key' => 'cfg_'.$c->id,
            'handle' => $c->handle,
            'name' => $c->name,
            'version_id' => $c->published_version_id,
            'display_mode' => $c->display_mode,
            'variant_mode' => $c->variant_mode,
            'base_product_id' => $c->base_product_id,
            'base_variant_id' => $c->base_variant_id,
            'display' => $c->display ?? [],
            'definition' => self::publicDefinition($definition),
        ];
    }

    /** Strips merchant-only fields (SKU codes, internal notes) before the definition goes public. */
    public static function publicDefinition(array $definition): array
    {
        foreach ($definition['steps'] ?? [] as $si => $step) {
            foreach ($step['fields'] ?? [] as $fi => $field) {
                unset($definition['steps'][$si]['fields'][$fi]['internal_note']);
                foreach ($field['options'] ?? [] as $oi => $o) {
                    unset($definition['steps'][$si]['fields'][$fi]['options'][$oi]['sku'], $definition['steps'][$si]['fields'][$fi]['options'][$oi]['internal_note']);
                }
            }
        }
        unset($definition['notes']);

        return $definition;
    }

    private function assignments($published): array
    {
        $rows = [];
        foreach ($published as $c) {
            foreach ($c->assignments as $a) {
                $id = in_array($a->target_type, ['product', 'collection'], true) ? ConfigurationSigner::numericId($a->target_id) : mb_strtolower($a->target_id);
                $rows[] = ['t' => $a->target_type, 'id' => $id, 'c' => 'cfg_'.$c->id, 'p' => $a->priority];
            }
        }
        usort($rows, fn ($x, $y) => $x['p'] <=> $y['p']);

        return $rows;
    }

    private function settings(Shop $shop): array
    {
        return [
            'proxy' => '/'.config('composa.shopify.proxy_prefix').'/'.config('composa.shopify.proxy_subpath'),
            'cart_mode' => $shop->cart_mode,
            'currency' => $shop->currency,
            'appearance' => $shop->setting('appearance', ['inherit_theme' => true]),
            'cart' => $shop->setting('cart', ['after_add' => 'drawer', 'summary_lines' => 6]),
            'uploads' => ['max_mb' => (int) $shop->setting('files.max_mb', config('composa.uploads.max_mb')), 'types' => $shop->setting('files.types', config('composa.uploads.allowed'))],
        ];
    }

    private function json(string $owner, string $ns, string $key, array $value): array
    {
        return ['ownerId' => $owner, 'namespace' => $ns, 'key' => $key, 'type' => 'json', 'value' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
    }

    private function removeStale(AdminApi $api, string $ownerId, string $ns, array $keepIds, Shop $shop): void
    {
        $existing = $api->query('query($ns: String!) { shop { metafields(first: 250, namespace: $ns) { nodes { key } } } }', ['ns' => $ns])['shop']['metafields']['nodes'] ?? [];
        $stale = collect($existing)->pluck('key')
            ->filter(fn ($k) => str_starts_with($k, 'cfg_') && ! in_array((int) substr($k, 4), $keepIds, true))
            ->map(fn ($k) => ['ownerId' => $ownerId, 'namespace' => $ns, 'key' => $k])->values()->all();
        if ($stale) {
            $api->mutate('mutation($m: [MetafieldIdentifierInput!]!) { metafieldsDelete(metafields: $m) { deletedMetafields { key } userErrors { field message } } }', ['m' => $stale], 'metafieldsDelete');
        }
    }
}
