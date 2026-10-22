<?php

namespace App\Services;

use App\Models\Shop;
use Illuminate\Support\Str;

/**
 * Turns a product's Shopify options (Color, Size, Carat…) into configurator steps. Each option
 * becomes a variant step whose answers resolve to one exact variant; its layout comes from the
 * shop's "default layout by option name" presets. Custom steps already in the definition are kept.
 */
class VariantMapper
{
    /** Default layout presets by option name, overridable per shop (settings.variant_presets). */
    public const PRESETS = [
        ['match' => ['color', 'colour', 'shade'], 'type' => 'swatch', 'layout' => 'swatches'],
        ['match' => ['size'], 'type' => 'buttons', 'layout' => 'size_grid'],
        ['match' => ['material', 'fabric', 'finish'], 'type' => 'image_tiles', 'layout' => 'tiles'],
        ['match' => ['carat', 'weight', 'capacity'], 'type' => 'buttons', 'layout' => 'pills'],
        ['match' => ['length', 'width', 'dimensions'], 'type' => 'dropdown', 'layout' => 'dropdown'],
    ];

    public static function presetFor(string $optionName, ?Shop $shop = null): array
    {
        $presets = $shop?->setting('variant_presets') ?: self::PRESETS;
        $name = Str::lower(trim($optionName));
        foreach ($presets as $p) {
            foreach ((array) ($p['match'] ?? []) as $m) {
                if ($name === Str::lower($m)) {
                    return ['type' => $p['type'], 'layout' => $p['layout']];
                }
            }
        }

        return ['type' => 'buttons', 'layout' => 'buttons'];
    }

    /**
     * @param  array{options:list<array{name:string,values:list<string>}>}  $product
     * @param  array<string,array{show?:string,layout?:string,type?:string,order?:int}>  $choices  per option name
     */
    public function apply(array $definition, array $product, array $choices = [], ?Shop $shop = null): array
    {
        $custom = array_values(array_filter($definition['steps'] ?? [], fn ($s) => ($s['source'] ?? 'custom') !== 'shopify_option'));
        $existing = [];
        foreach ($definition['steps'] ?? [] as $s) {
            if (($s['source'] ?? null) === 'shopify_option') {
                $existing[$s['shopify_option'] ?? ''] = $s;
            }
        }

        $variantSteps = [];
        foreach ($product['options'] ?? [] as $i => $option) {
            $name = (string) $option['name'];
            if ($name === 'Title' && ($option['values'] ?? []) === ['Default Title']) {
                continue;
            }
            $choice = $choices[$name] ?? [];
            if (($choice['show'] ?? 'pick') === 'skip') {
                continue;
            }
            $preset = self::presetFor($name, $shop);
            $old = $existing[$name] ?? null;
            $oldField = $old['fields'][0] ?? [];
            $oldOptions = collect($oldField['options'] ?? [])->keyBy('value');
            $key = $oldField['key'] ?? (Str::snake(Str::ascii($name)) ?: 'option_'.($i + 1));

            $variantSteps[] = [
                'order' => $choice['order'] ?? $i,
                'step' => [
                    'key' => $old['key'] ?? 's_'.$key,
                    'title' => $old['title'] ?? $name,
                    'help' => $old['help'] ?? '',
                    'source' => 'shopify_option',
                    'shopify_option' => $name,
                    'fields' => [[
                        'key' => $key,
                        'type' => $choice['type'] ?? $oldField['type'] ?? $preset['type'],
                        'layout' => $choice['layout'] ?? $oldField['layout'] ?? $preset['layout'],
                        'label' => $oldField['label'] ?? $name,
                        'required' => true,
                        'variant_option' => $name,
                        'mode' => match ($choice['show'] ?? 'pick') { 'prefill' => 'prefill', 'fixed' => 'fixed', default => 'pick' },
                        'fixed_value' => $choice['fixed_value'] ?? null,
                        'options' => array_map(fn ($v) => array_merge(['value' => (string) $v, 'label' => (string) $v], (array) ($oldOptions[(string) $v] ?? [])), $option['values'] ?? []),
                    ]],
                ],
            ];
        }
        usort($variantSteps, fn ($a, $b) => $a['order'] <=> $b['order']);

        $definition['steps'] = array_merge(array_column($variantSteps, 'step'), $custom);

        return $definition;
    }
}
