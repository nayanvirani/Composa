<?php

namespace App\Engine;

/**
 * Structural checks on a configurator definition before it is saved or published: unique keys,
 * known types, valid references in rules, layers and price tables, and formulas that parse and only
 * read known variables. Returns problems; an empty list means the definition can be published.
 */
final class DefinitionValidator
{
    public const FIELD_TYPES = ['image_tiles', 'swatch', 'buttons', 'dropdown', 'radio', 'checkbox', 'text', 'number', 'dimensions', 'file', 'product_picker'];

    public const CHOICE_TYPES = ['image_tiles', 'swatch', 'buttons', 'dropdown', 'radio', 'checkbox'];

    public const PRICE_TYPES = ['fixed', 'percent', 'per_unit', 'per_char', 'tiered', 'table', 'formula'];

    public const OPS = ['eq', 'neq', 'in', 'not_in', 'contains', 'empty', 'not_empty', 'gt', 'gte', 'lt', 'lte'];

    public const ACTIONS = ['show_step', 'hide_step', 'show_field', 'hide_field', 'show_option', 'hide_option', 'disable_option', 'enable_option', 'require', 'unrequire', 'set_default', 'add_price', 'set_layer', 'set_variant_option'];

    /** @return list<array{path:string,message:string}> */
    public static function validate(array $definition): array
    {
        $errors = [];
        $err = function (string $path, string $message) use (&$errors) {
            $errors[] = ['path' => $path, 'message' => $message];
        };

        $steps = $definition['steps'] ?? null;
        if (! is_array($steps) || $steps === []) {
            $err('steps', 'Add at least one step.');

            return $errors;
        }

        $stepKeys = [];
        $fields = [];
        foreach ($steps as $si => $step) {
            $key = (string) ($step['key'] ?? '');
            if (! preg_match('/^[a-z][a-z0-9_]{0,47}$/', $key)) {
                $err("steps.$si.key", 'Step keys use lowercase letters, numbers and underscores.');
            }
            if (isset($stepKeys[$key])) {
                $err("steps.$si.key", "Step key “{$key}” is used twice.");
            }
            $stepKeys[$key] = true;
            if (trim((string) ($step['title'] ?? '')) === '') {
                $err("steps.$si.title", 'Every step needs a title.');
            }
            if (! empty($step['parent']) && ! in_array($step['parent'], array_column($steps, 'key'), true)) {
                $err("steps.$si.parent", 'Nested step points at a missing parent step.');
            }
            if (empty($step['fields'])) {
                $err("steps.$si.fields", "Step “{$step['title']}” has no fields.");
            }
            foreach ($step['fields'] ?? [] as $fi => $field) {
                $path = "steps.$si.fields.$fi";
                $fkey = (string) ($field['key'] ?? '');
                if (! preg_match('/^[a-z][a-z0-9_]{0,47}$/', $fkey)) {
                    $err("$path.key", 'Field keys use lowercase letters, numbers and underscores (they are formula variables).');
                }
                if (isset($fields[$fkey])) {
                    $err("$path.key", "Field key “{$fkey}” is used twice.");
                }
                if (in_array($fkey, ['base', 'options', 'subtotal', 'quantity'], true) || in_array($fkey, Formula::FUNCTIONS, true)) {
                    $err("$path.key", "“{$fkey}” is reserved; choose another key.");
                }
                $fields[$fkey] = $field;
                $type = $field['type'] ?? '';
                if (! in_array($type, self::FIELD_TYPES, true)) {
                    $err("$path.type", 'Unknown input type.');
                }
                if (in_array($type, self::CHOICE_TYPES, true)) {
                    if (empty($field['options'])) {
                        $err("$path.options", "“".($field['label'] ?? $fkey)."” needs at least one option.");
                    }
                    $values = [];
                    foreach ($field['options'] ?? [] as $oi => $o) {
                        $v = (string) ($o['value'] ?? '');
                        if ($v === '') {
                            $err("$path.options.$oi.value", 'Every option needs a value.');
                        }
                        if (isset($values[$v])) {
                            $err("$path.options.$oi.value", "Option value “{$v}” is used twice.");
                        }
                        $values[$v] = true;
                        self::checkPriceRule($o['price_rule'] ?? null, "$path.options.$oi.price_rule", $definition, $err);
                    }
                }
                self::checkPriceRule($field['price_rule'] ?? null, "$path.price_rule", $definition, $err);
                if (! empty($field['validation']['pattern']) && @preg_match('~'.str_replace('~', '\~', (string) $field['validation']['pattern']).'~u', '') === false) {
                    $err("$path.validation.pattern", 'The allowed-characters pattern is not a valid regular expression.');
                }
            }
        }

        foreach ($definition['rules'] ?? [] as $ri => $rule) {
            self::checkCondition($rule['conditions'] ?? null, "rules.$ri.conditions", $fields, $err);
            if (empty($rule['actions'])) {
                $err("rules.$ri.actions", 'A rule needs at least one action.');
            }
            foreach ($rule['actions'] ?? [] as $ai => $a) {
                $type = $a['type'] ?? '';
                if (! in_array($type, self::ACTIONS, true)) {
                    $err("rules.$ri.actions.$ai", 'Unknown action.');

                    continue;
                }
                if (in_array($type, ['show_step', 'hide_step'], true) && ! isset($stepKeys[$a['step'] ?? ''])) {
                    $err("rules.$ri.actions.$ai", 'Rule points at a missing step.');
                }
                if (isset($a['field']) && ! isset($fields[$a['field']])) {
                    $err("rules.$ri.actions.$ai", 'Rule points at a missing field.');
                }
                if (isset($a['option'], $a['field'], $fields[$a['field']]) && ! in_array((string) $a['option'], array_map(fn ($o) => (string) $o['value'], $fields[$a['field']]['options'] ?? []), true)) {
                    $err("rules.$ri.actions.$ai", 'Rule points at a missing option.');
                }
            }
        }

        foreach ($definition['price_rules'] ?? [] as $pi => $rule) {
            self::checkPriceRule($rule, "price_rules.$pi", $definition, $err);
            self::checkCondition($rule['when'] ?? null, "price_rules.$pi.when", $fields, $err);
        }

        foreach ($definition['layers'] ?? [] as $li => $layer) {
            if (! empty($layer['field']) && ! isset($fields[$layer['field']])) {
                $err("layers.$li.field", 'Layer is driven by a missing field.');
            }
        }

        if (! empty($definition['settings']['formula'])) {
            self::checkFormula((string) $definition['settings']['formula'], 'settings.formula', $definition, $err);
        }

        return $errors;
    }

    private static function checkPriceRule(?array $rule, string $path, array $definition, callable $err): void
    {
        if (! $rule || empty($rule['type'])) {
            return;
        }
        if (! in_array($rule['type'], self::PRICE_TYPES, true)) {
            $err($path, 'Unknown price rule type.');

            return;
        }
        if ($rule['type'] === 'table' && ! isset($definition['tables'][$rule['table'] ?? ''])) {
            $err($path, 'Price table not found.');
        }
        if ($rule['type'] === 'tiered' && empty($rule['tiers'])) {
            $err($path, 'Add at least one tier.');
        }
        if ($rule['type'] === 'formula') {
            self::checkFormula((string) ($rule['expression'] ?? ''), $path.'.expression', $definition, $err);
        }
    }

    private static function checkFormula(string $formula, string $path, array $definition, callable $err): void
    {
        try {
            $vars = Formula::variables($formula);
        } catch (FormulaError $e) {
            $err($path, 'Formula error: '.$e->getMessage());

            return;
        }
        $known = ['base', 'options', 'subtotal', 'quantity', ...array_keys($definition['settings']['variables'] ?? [])];
        foreach (Engine::fieldsOf($definition) as ['field' => $f]) {
            $known[] = $f['key'];
            if (($f['type'] ?? '') === 'dimensions') {
                array_push($known, $f['key'].'_w', $f['key'].'_h');
            }
            foreach ($f['options'] ?? [] as $o) {
                array_push($known, ...array_keys($o['vars'] ?? []));
            }
        }
        foreach (array_diff($vars, $known) as $unknown) {
            $err($path, "Formula uses “{$unknown}”, which is not a field, option variable or constant.");
        }
    }

    private static function checkCondition(?array $node, string $path, array $fields, callable $err): void
    {
        if (! $node) {
            return;
        }
        foreach (['all', 'any'] as $k) {
            if (isset($node[$k])) {
                foreach ($node[$k] as $i => $child) {
                    self::checkCondition($child, "$path.$k.$i", $fields, $err);
                }

                return;
            }
        }
        $field = $node['field'] ?? '';
        if (! in_array($field, ['$quantity', '$country'], true) && ! isset($fields[$field])) {
            $err($path, 'Condition uses a missing field.');
        }
        if (! in_array($node['op'] ?? '', self::OPS, true)) {
            $err($path, 'Unknown condition operator.');
        }
    }
}
