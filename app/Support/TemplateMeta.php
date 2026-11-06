<?php

namespace App\Support;

/** Display facts about a configurator definition for the website (pricing style, step kinds). */
final class TemplateMeta
{
    private const LABELS = ['formula' => 'Formula', 'table' => 'Lookup table', 'tiered' => 'Tiered', 'per_unit' => 'Per unit', 'per_char' => 'Per character', 'linked' => 'Linked products', 'variant' => 'Variants', 'percent' => 'Fixed', 'fixed' => 'Fixed'];

    /** The most advanced pricing type a definition uses, as a short label. */
    public static function pricing(array $definition): string
    {
        $found = [];
        $collect = function ($rule) use (&$found) {
            if (is_array($rule) && ! empty($rule['type'])) {
                $found[$rule['type']] = true;
            }
        };
        foreach ($definition['steps'] ?? [] as $step) {
            foreach ($step['fields'] ?? [] as $field) {
                $collect($field['price'] ?? null);
                if (! empty($field['variant_option'])) {
                    $found['variant'] = true;
                }
                foreach ($field['options'] ?? [] as $o) {
                    $collect($o['price'] ?? null);
                    if (! empty($o['linked_variant_id'])) {
                        $found['linked'] = true;
                    }
                }
            }
        }
        foreach ($definition['price_rules'] ?? [] as $rule) {
            $collect($rule);
        }
        if (! empty($definition['settings']['formula'])) {
            $found['formula'] = true;
        }
        foreach (array_keys(self::LABELS) as $type) {
            if (isset($found[$type])) {
                return self::LABELS[$type];
            }
        }

        return 'Fixed';
    }

    /** Steps as [title, kind] where kind is SHOPIFY (variant option), SYSTEM (review) or CUSTOM. */
    public static function steps(array $definition): array
    {
        $out = [];
        foreach ($definition['steps'] ?? [] as $step) {
            $variant = collect($step['fields'] ?? [])->contains(fn ($f) => ! empty($f['variant_option']));
            $out[] = [$step['title'] ?? $step['key'], $variant ? 'SHOPIFY' : 'CUSTOM'];
        }
        $out[] = ['Review', 'SYSTEM'];

        return $out;
    }
}
