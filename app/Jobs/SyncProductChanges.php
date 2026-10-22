<?php

namespace App\Jobs;

use App\Models\Configurator;
use App\Models\Shop;
use App\Services\ProductCatalog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * products/update and products/delete: refreshes cached variant prices and records Shopify option
 * changes (new or removed values, renamed options) on the configurators built on that product, for
 * the "Changes from Shopify" list in the merchant app.
 */
class SyncProductChanges implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $shopId, public string $topic, public array $product) {}

    public function handle(ProductCatalog $catalog): void
    {
        $shop = Shop::query()->find($this->shopId);
        if (! $shop) {
            return;
        }
        $gid = (string) ($this->product['admin_graphql_api_id'] ?? 'gid://shopify/Product/'.($this->product['id'] ?? ''));
        $catalog->forget($shop, $gid);

        $configurators = Configurator::query()->where('shop_id', $shop->id)->where('base_product_id', $gid)->with('draftVersion')->get();
        foreach ($configurators as $configurator) {
            $display = $configurator->display ?? [];
            if ($this->topic === 'products/delete') {
                $display['base_product_missing'] = true;
                $configurator->forceFill(['display' => $display])->save();

                continue;
            }
            $current = collect($this->product['options'] ?? [])->mapWithKeys(fn ($o) => [(string) $o['name'] => array_map('strval', $o['values'] ?? [])])->all();
            $display['variant_changes'] = array_slice(array_merge(self::diff($configurator->draftVersion?->definition ?? [], $current), $display['variant_changes'] ?? []), 0, 50);
            $display['variant_synced_at'] = now()->toIso8601String();
            unset($display['base_product_missing']);
            $configurator->forceFill(['display' => $display])->save();
        }
    }

    /** @return list<array{kind:string,option:string,value:?string,at:string}> */
    public static function diff(array $definition, array $current): array
    {
        $changes = [];
        $mapped = [];
        foreach ($definition['steps'] ?? [] as $step) {
            foreach ($step['fields'] ?? [] as $field) {
                if (empty($field['variant_option'])) {
                    continue;
                }
                $name = (string) $field['variant_option'];
                $mapped[$name] = true;
                $known = array_map(fn ($o) => (string) $o['value'], $field['options'] ?? []);
                if (! array_key_exists($name, $current)) {
                    $changes[] = ['kind' => 'option_missing', 'option' => $name, 'value' => null, 'at' => now()->toIso8601String()];

                    continue;
                }
                foreach (array_diff($current[$name], $known) as $value) {
                    $changes[] = ['kind' => 'value_added', 'option' => $name, 'value' => $value, 'at' => now()->toIso8601String()];
                }
                foreach (array_diff($known, $current[$name]) as $value) {
                    $changes[] = ['kind' => 'value_removed', 'option' => $name, 'value' => $value, 'at' => now()->toIso8601String()];
                }
            }
        }
        foreach (array_keys($current) as $name) {
            if ($mapped && ! isset($mapped[$name]) && $name !== 'Title') {
                $changes[] = ['kind' => 'option_unmapped', 'option' => $name, 'value' => null, 'at' => now()->toIso8601String()];
            }
        }

        return $changes;
    }
}
