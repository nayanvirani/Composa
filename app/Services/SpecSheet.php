<?php

namespace App\Services;

use App\Engine\Engine;

/**
 * Turns a definition and answers into readable rows ("Metal: 18K Rose Gold") for the production
 * spec, the PDF and the cart line properties. Option values become their labels.
 */
final class SpecSheet
{
    /** @return list<array{label:string,value:string,step:string}> */
    public static function rows(array $definition, array $answers, bool $onlySummary = false): array
    {
        $rows = [];
        foreach ($definition['steps'] ?? [] as $step) {
            if (! empty($step['repeatable'])) {
                foreach ($answers[$step['key']] ?? [] as $i => $item) {
                    $parts = [];
                    foreach ($step['fields'] ?? [] as $field) {
                        $value = self::display($field, $item[$field['key']] ?? null);
                        if ($value !== '') {
                            $parts[] = $value;
                        }
                    }
                    if ($parts) {
                        $rows[] = ['label' => ($step['title'] ?? $step['key']).' '.($i + 1), 'value' => implode(' · ', $parts), 'step' => (string) ($step['title'] ?? '')];
                    }
                }

                continue;
            }
            foreach ($step['fields'] ?? [] as $field) {
                if ($onlySummary && (($field['show_in_cart'] ?? true) === false)) {
                    continue;
                }
                $value = self::display($field, $answers[$field['key']] ?? null);
                if ($value !== '') {
                    $rows[] = ['label' => (string) (($field['label'] ?? '') ?: ($step['title'] ?? $field['key'])), 'value' => $value, 'step' => (string) ($step['title'] ?? '')];
                }
            }
        }

        return $rows;
    }

    public static function display(array $field, mixed $value): string
    {
        if (Engine::isEmpty($value)) {
            return '';
        }
        $label = function ($v) use ($field) {
            foreach ($field['options'] ?? [] as $o) {
                if ((string) $o['value'] === (string) $v) {
                    return (string) ($o['label'] ?? $o['value']);
                }
            }

            return (string) $v;
        };

        return match ($field['type'] ?? '') {
            'checkbox' => implode(', ', array_map($label, (array) $value)),
            'dimensions' => (is_array($value) ? ($value['w'] ?? '?').' × '.($value['h'] ?? '?') : (string) $value).(! empty($field['unit']) ? ' '.$field['unit'] : ''),
            'file' => is_array($value) ? (string) ($value['name'] ?? 'File') : 'File',
            'text' => '“'.(string) $value.'”',
            'product_picker' => is_array($value) ? (string) ($value['title'] ?? $value['id'] ?? '') : (string) $value,
            default => is_scalar($value) ? $label($value) : '',
        };
    }
}
