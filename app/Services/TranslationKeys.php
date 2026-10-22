<?php

namespace App\Services;

/**
 * The translatable strings of a configurator, as stable keys (step.{key}.title, option.{field}.{value}.label…).
 * Translations live in definition.translations[locale][key]; missing ones fall back to the default text.
 */
final class TranslationKeys
{
    public const UI = [
        'ui.next' => 'Next',
        'ui.back' => 'Back',
        'ui.add_to_cart' => 'Add to cart',
        'ui.review' => 'Review',
        'ui.price_breakdown' => 'Price breakdown',
        'ui.total' => 'Total',
        'ui.quantity' => 'Quantity',
        'ui.edit' => 'Edit',
        'ui.required' => 'Please choose an option',
        'ui.sold_out' => 'Sold out',
        'ui.unavailable' => 'Unavailable',
        'ui.added' => 'Added to your cart',
        'ui.upload' => 'Upload a file',
        'ui.add_another' => 'Add another',
        'ui.remove' => 'Remove',
        'ui.customize' => 'Customize',
    ];

    /** @return array<string,string> key => default text */
    public static function extract(array $definition): array
    {
        $out = [];
        foreach ($definition['steps'] ?? [] as $step) {
            $out['step.'.$step['key'].'.title'] = (string) ($step['title'] ?? '');
            if (! empty($step['help'])) {
                $out['step.'.$step['key'].'.help'] = (string) $step['help'];
            }
            foreach ($step['fields'] ?? [] as $field) {
                $out['field.'.$field['key'].'.label'] = (string) ($field['label'] ?? '');
                foreach (['help', 'placeholder'] as $k) {
                    if (! empty($field[$k])) {
                        $out['field.'.$field['key'].'.'.$k] = (string) $field[$k];
                    }
                }
                foreach ($field['options'] ?? [] as $o) {
                    $out['option.'.$field['key'].'.'.$o['value'].'.label'] = (string) ($o['label'] ?? $o['value']);
                    if (! empty($o['description'])) {
                        $out['option.'.$field['key'].'.'.$o['value'].'.description'] = (string) $o['description'];
                    }
                }
            }
        }
        foreach ($definition['rules'] ?? [] as $rule) {
            if (! empty($rule['message'])) {
                $out['rule.'.$rule['id'].'.message'] = (string) $rule['message'];
            }
        }

        return array_filter($out, fn ($v) => $v !== '') + self::UI;
    }

    /** @return array{total:int,done:int,percent:int} */
    public static function coverage(array $definition, string $locale): array
    {
        $keys = array_keys(self::extract($definition));
        $done = count(array_filter($keys, fn ($k) => ! empty($definition['translations'][$locale][$k] ?? null)));

        return ['total' => count($keys), 'done' => $done, 'percent' => $keys ? (int) round($done * 100 / count($keys)) : 100];
    }
}
