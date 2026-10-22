<?php

namespace App\Services;

use App\Models\Shop;
use App\Shopify\AdminApi;
use Illuminate\Support\Facades\Cache;

/**
 * Reads products from Shopify for the builder and for server-side pricing. Variant prices used to
 * sign a configuration always come from here (shop currency), never from the browser.
 */
class ProductCatalog
{
    private const PRODUCT = <<<'GQL'
    query ComposaProduct($id: ID!) {
      product(id: $id) {
        id title handle vendor tags productType status
        featuredImage { url }
        options { name position optionValues { name } }
        collections(first: 50) { nodes { id } }
        variants(first: 250) {
          nodes { id title sku price availableForSale inventoryQuantity inventoryPolicy image { url } selectedOptions { name value } }
        }
      }
    }
    GQL;

    /** @return array<string,mixed>|null */
    public function product(Shop $shop, string $gid): ?array
    {
        return Cache::remember($this->key($shop, $gid), 60, function () use ($shop, $gid) {
            $p = AdminApi::for($shop)->query(self::PRODUCT, ['id' => $gid])['product'] ?? null;
            if (! $p) {
                return null;
            }

            return [
                'id' => $p['id'],
                'title' => $p['title'],
                'handle' => $p['handle'],
                'vendor' => $p['vendor'],
                'tags' => $p['tags'] ?? [],
                'type' => $p['productType'] ?? null,
                'status' => $p['status'] ?? null,
                'image' => $p['featuredImage']['url'] ?? null,
                'collections' => array_column($p['collections']['nodes'] ?? [], 'id'),
                'options' => array_map(fn ($o) => ['name' => $o['name'], 'values' => array_column($o['optionValues'] ?? [], 'name')], $p['options'] ?? []),
                'variants' => array_map(fn ($v) => [
                    'id' => $v['id'],
                    'title' => $v['title'],
                    'sku' => $v['sku'],
                    'price_cents' => (int) round((float) $v['price'] * 100),
                    'available' => (bool) $v['availableForSale'],
                    'inventory' => $v['inventoryQuantity'],
                    'image' => $v['image']['url'] ?? null,
                    'options' => collect($v['selectedOptions'] ?? [])->mapWithKeys(fn ($o) => [$o['name'] => $o['value']])->all(),
                ], $p['variants']['nodes'] ?? []),
            ];
        });
    }

    public function forget(Shop $shop, string $gid): void
    {
        Cache::forget($this->key($shop, $gid));
    }

    /** Variants in the shape the engine expects. */
    public function engineVariants(?array $product): array
    {
        return array_map(fn ($v) => ['id' => $v['id'], 'price_cents' => $v['price_cents'], 'available' => $v['available'], 'options' => $v['options']], $product['variants'] ?? []);
    }

    public function variant(?array $product, ?string $variantId): ?array
    {
        foreach ($product['variants'] ?? [] as $v) {
            if ($v['id'] === $variantId || ConfigurationSigner::numericId($v['id']) === ConfigurationSigner::numericId((string) $variantId)) {
                return $v;
            }
        }

        return null;
    }

    /** @return array{items:list<array<string,mixed>>,next:?string} */
    public function search(Shop $shop, string $type, string $query = '', ?string $after = null): array
    {
        $api = AdminApi::for($shop);
        $q = trim($query);

        if ($type === 'product') {
            $data = $api->query('query($q: String, $after: String) { products(first: 25, after: $after, query: $q, sortKey: TITLE) { nodes { id title vendor status featuredImage { url } variantsCount { count } priceRangeV2 { minVariantPrice { amount } } } pageInfo { hasNextPage endCursor } } }', ['q' => $q ?: null, 'after' => $after])['products'];

            return ['items' => array_map(fn ($p) => ['id' => $p['id'], 'label' => $p['title'], 'meta' => $p['vendor'].' · '.($p['variantsCount']['count'] ?? 1).' variants', 'image' => $p['featuredImage']['url'] ?? null, 'price_cents' => (int) round((float) ($p['priceRangeV2']['minVariantPrice']['amount'] ?? 0) * 100), 'count' => 1], $data['nodes']), 'next' => $data['pageInfo']['hasNextPage'] ? $data['pageInfo']['endCursor'] : null];
        }
        if ($type === 'collection') {
            $data = $api->query('query($q: String, $after: String) { collections(first: 25, after: $after, query: $q) { nodes { id title image { url } productsCount { count } } pageInfo { hasNextPage endCursor } } }', ['q' => $q ?: null, 'after' => $after])['collections'];

            return ['items' => array_map(fn ($c) => ['id' => $c['id'], 'label' => $c['title'], 'meta' => ($c['productsCount']['count'] ?? 0).' products', 'image' => $c['image']['url'] ?? null, 'count' => (int) ($c['productsCount']['count'] ?? 0)], $data['nodes']), 'next' => $data['pageInfo']['hasNextPage'] ? $data['pageInfo']['endCursor'] : null];
        }
        $root = $type === 'tag' ? 'productTags' : 'productVendors';
        $values = $api->query("query { {$root}(first: 250) { nodes } }")[$root]['nodes'] ?? [];
        $values = array_values(array_filter($values, fn ($v) => $q === '' || str_contains(mb_strtolower($v), mb_strtolower($q))));

        return ['items' => array_map(fn ($v) => ['id' => $v, 'label' => $v, 'meta' => $type === 'tag' ? 'Tag' : 'Vendor', 'image' => null, 'count' => null], array_slice($values, 0, 50)), 'next' => null];
    }

    /** How many products an assignment target covers (counts toward the "assigned products" limit). */
    public function countFor(Shop $shop, string $type, string $id): int
    {
        if ($type === 'product') {
            return 1;
        }
        $api = AdminApi::for($shop);
        if ($type === 'collection') {
            return (int) ($api->query('query($id: ID!) { collection(id: $id) { productsCount { count } } }', ['id' => $id])['collection']['productsCount']['count'] ?? 0);
        }
        $field = $type === 'tag' ? 'tag' : 'vendor';
        $search = $field.":'".str_replace("'", "\\'", $id)."'";

        return (int) ($api->query('query($q: String!) { productsCount(query: $q) { count } }', ['q' => $search])['productsCount']['count'] ?? 0);
    }

    private function key(Shop $shop, string $gid): string
    {
        return 'product:'.$shop->id.':'.md5($gid);
    }
}
