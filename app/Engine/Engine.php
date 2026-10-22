<?php

namespace App\Engine;

/**
 * Composa configurator engine (server). Twin of engine/js/src/engine.js: rules, defaults,
 * Shopify variant resolution, validation and price. The browser shows the price instantly; this
 * class recomputes it before it is signed. Both must produce identical output for every fixture
 * in tests/engine/fixtures — change them together.
 *
 * Money is integer minor units (cents). Definitions store amounts in major units.
 */
final class Engine
{
    private const MAX_PASSES = 5;

    private const CHOICE_TYPES = ['image_tiles', 'swatch', 'buttons', 'dropdown', 'radio'];

    private const MULTI_TYPES = ['checkbox'];

    public static function cents(mixed $major): int
    {
        return (int) Formula::roundHalfUp(self::num($major) * 100);
    }

    public static function isEmpty(mixed $v): bool
    {
        return $v === null || $v === '' || (is_array($v) && $v === []);
    }

    /**
     * @param  array<string,mixed>  $definition
     * @param  array<string,mixed>  $rawAnswers
     * @param  array{base_price_cents?:int,quantity?:int,variants?:list<array<string,mixed>>,country?:string}  $context
     * @return array<string,mixed>
     */
    public static function evaluate(array $definition, array $rawAnswers = [], array $context = []): array
    {
        $quantity = max(1, (int) floor(self::num($context['quantity'] ?? 1)) ?: 1);
        $ctx = ['quantity' => $quantity, 'country' => $context['country'] ?? null];
        $raw = $rawAnswers;
        $answers = $raw;
        $variants = $context['variants'] ?? null;
        $state = self::baseState($definition);
        $availability = [];

        for ($pass = 0; $pass < self::MAX_PASSES; $pass++) {
            $state = self::baseState($definition);
            foreach ($definition['rules'] ?? [] as $rule) {
                if (($rule['enabled'] ?? true) === false) {
                    continue;
                }
                if (self::matches($rule['conditions'] ?? null, $answers, $ctx)) {
                    foreach ($rule['actions'] ?? [] as $action) {
                        self::applyAction($state, $action);
                    }
                }
            }
            $availability = self::variantAvailability($definition, $answers, $variants);
            $next = self::clean($definition, $raw, $state, $availability);
            if (self::same($next, $answers)) {
                $answers = $next;
                break;
            }
            $answers = $next;
        }

        $errors = [];
        foreach ($definition['steps'] ?? [] as $step) {
            if (! ($state['steps'][$step['key']]['visible'] ?? false)) {
                continue;
            }
            if (! empty($step['repeatable'])) {
                $items = $answers[$step['key']] ?? [];
                if (! empty($step['required']) && ! $items) {
                    $errors[] = ['field' => $step['key'], 'code' => 'required'];
                }
                foreach ($items as $i => $item) {
                    foreach ($step['fields'] ?? [] as $field) {
                        self::validateField($field, ['required' => ! empty($field['required'])], $item[$field['key']] ?? null, $step['key'].'.'.$i.'.', $errors);
                    }
                }

                continue;
            }
            foreach ($step['fields'] ?? [] as $field) {
                if ($state['fields'][$field['key']]['visible']) {
                    self::validateField($field, $state['fields'][$field['key']], $answers[$field['key']] ?? null, '', $errors);
                }
            }
        }

        [$needed, $variant] = self::resolveVariant($definition, $answers, $variants);
        $hasRequired = (bool) array_filter($errors, fn ($e) => $e['code'] === 'required');
        if ($needed && $variant === null && ! $hasRequired) {
            $errors[] = ['field' => null, 'code' => 'variant_unavailable'];
        }
        if ($variant !== null && ($variant['available'] ?? true) === false) {
            $errors[] = ['field' => null, 'code' => 'variant_unavailable'];
        }
        $base = $variant !== null ? (int) $variant['price_cents'] : (int) ($context['base_price_cents'] ?? 0);

        $price = self::priceOf($definition, $answers, $state, $base, $quantity, $ctx, $errors);

        $fields = [];
        foreach ($state['fields'] as $key => $fs) {
            $opts = [];
            foreach ($fs['options'] as $value => $o) {
                $stock = isset($availability[$key]) ? (($availability[$key][(string) $value] ?? true) !== false) : true;
                $opts[(string) $value] = ['visible' => $o['visible'], 'disabled' => $o['disabled'] || ! $stock];
            }
            $fields[$key] = ['visible' => $fs['visible'], 'required' => $fs['required'], 'options' => $opts];
        }

        $visible = [];
        foreach ($definition['steps'] ?? [] as $s) {
            if ($state['steps'][$s['key']]['visible'] ?? false) {
                $visible[] = $s['key'];
            }
        }

        return [
            'answers' => $answers,
            'visible_steps' => $visible,
            'fields' => $fields,
            'errors' => $errors,
            'valid' => $errors === [],
            'variant_id' => $variant['id'] ?? null,
            'price' => $price,
            'layers' => self::layersOf($definition, $answers, $state),
        ];
    }

    /** @return list<array{step:array,field:array}> */
    public static function fieldsOf(array $definition): array
    {
        $out = [];
        foreach ($definition['steps'] ?? [] as $step) {
            foreach ($step['fields'] ?? [] as $field) {
                $out[] = ['step' => $step, 'field' => $field];
            }
        }

        return $out;
    }

    public static function matches(?array $node, array $answers, array $ctx = []): bool
    {
        if ($node === null || $node === []) {
            return true;
        }
        if (isset($node['all']) && is_array($node['all'])) {
            foreach ($node['all'] as $n) {
                if (! self::matches($n, $answers, $ctx)) {
                    return false;
                }
            }

            return true;
        }
        if (isset($node['any']) && is_array($node['any'])) {
            foreach ($node['any'] as $n) {
                if (self::matches($n, $answers, $ctx)) {
                    return true;
                }
            }

            return false;
        }
        $field = $node['field'] ?? null;
        if ($field === '$quantity') {
            return self::compare($node['op'] ?? '', $ctx['quantity'] ?? 1, $node['value'] ?? null);
        }
        if ($field === '$country') {
            return self::compare($node['op'] ?? '', $ctx['country'] ?? '', $node['value'] ?? null);
        }
        $value = $field !== null && array_key_exists($field, $answers) ? $answers[$field] : null;

        return self::compare($node['op'] ?? '', $value, $node['value'] ?? null);
    }

    // ---- helpers ------------------------------------------------------------------------------

    private static function num(mixed $v): float
    {
        if (is_int($v) || is_float($v)) {
            return is_finite((float) $v) ? (float) $v : 0.0;
        }
        if (is_string($v) && trim($v) !== '' && is_numeric(trim($v))) {
            return (float) trim($v);
        }

        return 0.0;
    }

    private static function str(mixed $v): string
    {
        if (is_bool($v)) {
            return $v ? 'true' : 'false';
        }
        if (is_float($v)) {
            // Match JavaScript's String(number): integral floats print without a fraction and
            // others use the shortest round-trip form (json_encode honours serialize_precision -1).
            return floor($v) == $v && abs($v) < 1e15 ? (string) (int) $v : (string) json_encode($v);
        }

        return is_scalar($v) ? (string) $v : '';
    }

    private static function isList(mixed $v): bool
    {
        return is_array($v) && array_is_list($v);
    }

    /** @return array{w:float,h:float,area:float} */
    private static function dims(mixed $v): array
    {
        $w = is_array($v) ? self::num($v['w'] ?? 0) : 0.0;
        $h = is_array($v) ? self::num($v['h'] ?? 0) : 0.0;

        return ['w' => $w, 'h' => $h, 'area' => $w * $h];
    }

    private static function charCount(mixed $text, bool $countSpaces): int
    {
        $s = is_scalar($text) ? (string) $text : '';
        if (! $countSpaces) {
            $s = (string) preg_replace('/\s/u', '', $s);
        }

        return mb_strlen($s, 'UTF-8');
    }

    private static function compare(string $op, mixed $actual, mixed $expected): bool
    {
        $isList = self::isList($actual) && $actual !== [];
        $list = $isList ? array_map([self::class, 'str'], $actual) : (self::isEmpty($actual) ? [] : [self::str($actual)]);
        $exp = is_array($expected) ? array_map([self::class, 'str'], $expected) : [self::str($expected)];

        return match ($op) {
            'eq' => $isList ? in_array(self::str($expected), $list, true) : (! self::isEmpty($actual) && self::str($actual) === self::str($expected)),
            'neq' => $isList ? ! in_array(self::str($expected), $list, true) : (self::isEmpty($actual) || self::str($actual) !== self::str($expected)),
            'in' => (bool) array_intersect($exp, $list),
            'not_in' => ! array_intersect($exp, $list),
            'contains' => $isList ? in_array(self::str($expected), $list, true) : str_contains(mb_strtolower(self::str($actual)), mb_strtolower(self::str($expected))),
            'empty' => self::isEmpty($actual),
            'not_empty' => ! self::isEmpty($actual),
            'gt' => ! self::isEmpty($actual) && self::num($actual) > self::num($expected),
            'gte' => ! self::isEmpty($actual) && self::num($actual) >= self::num($expected),
            'lt' => ! self::isEmpty($actual) && self::num($actual) < self::num($expected),
            'lte' => ! self::isEmpty($actual) && self::num($actual) <= self::num($expected),
            default => false,
        };
    }

    private static function baseState(array $definition): array
    {
        $steps = [];
        $fields = [];
        foreach ($definition['steps'] ?? [] as $step) {
            $steps[$step['key']] = ['visible' => empty($step['hidden_by_default'])];
            foreach ($step['fields'] ?? [] as $field) {
                $options = [];
                foreach ($field['options'] ?? [] as $o) {
                    $options[self::str($o['value'])] = ['visible' => empty($o['hidden']), 'disabled' => ! empty($o['disabled'])];
                }
                $fields[$field['key']] = [
                    'visible' => empty($field['hidden_by_default']),
                    'required' => ! empty($field['required']),
                    'default' => $field['default'] ?? null,
                    'options' => $options,
                ];
            }
        }

        return ['steps' => $steps, 'fields' => $fields, 'priceAdds' => [], 'layers' => [], 'variantSets' => []];
    }

    private static function applyAction(array &$state, array $action): void
    {
        $fk = $action['field'] ?? null;
        $hasField = $fk !== null && isset($state['fields'][$fk]);
        $ok = $hasField && array_key_exists('option', $action) && $action['option'] !== null ? self::str($action['option']) : null;
        $hasOpt = $ok !== null && isset($state['fields'][$fk]['options'][$ok]);
        $sk = $action['step'] ?? null;
        $hasStep = $sk !== null && isset($state['steps'][$sk]);

        switch ($action['type'] ?? '') {
            case 'show_step': if ($hasStep) {
                $state['steps'][$sk]['visible'] = true;
            } break;
            case 'hide_step': if ($hasStep) {
                $state['steps'][$sk]['visible'] = false;
            } break;
            case 'show_field': if ($hasField) {
                $state['fields'][$fk]['visible'] = true;
            } break;
            case 'hide_field': if ($hasField) {
                $state['fields'][$fk]['visible'] = false;
            } break;
            case 'show_option': if ($hasOpt) {
                $state['fields'][$fk]['options'][$ok]['visible'] = true;
            } break;
            case 'hide_option': if ($hasOpt) {
                $state['fields'][$fk]['options'][$ok]['visible'] = false;
            } break;
            case 'disable_option': if ($hasOpt) {
                $state['fields'][$fk]['options'][$ok]['disabled'] = true;
            } break;
            case 'enable_option': if ($hasOpt) {
                $state['fields'][$fk]['options'][$ok]['disabled'] = false;
            } break;
            case 'require': if ($hasField) {
                $state['fields'][$fk]['required'] = true;
            } break;
            case 'unrequire': if ($hasField) {
                $state['fields'][$fk]['required'] = false;
            } break;
            case 'set_default': if ($hasField) {
                $state['fields'][$fk]['default'] = $action['value'] ?? null;
            } break;
            case 'add_price':
                $state['priceAdds'][] = ['label' => ($action['label'] ?? '') ?: 'Adjustment', 'amount' => $action['amount'] ?? null, 'percent' => $action['percent'] ?? null];
                break;
            case 'set_layer':
                $state['layers'][$action['layer'] ?? ''] = $action['image'] ?? null;
                break;
            case 'set_variant_option':
                $state['variantSets'][$action['option_name'] ?? ''] = $action['value'] ?? null;
                break;
        }
    }

    private static function optionUsable(array $fs, mixed $value): bool
    {
        $o = $fs['options'][self::str($value)] ?? null;

        return $o !== null && $o['visible'] && ! $o['disabled'];
    }

    private static function clean(array $definition, array $raw, array $state, array $availability): array
    {
        $out = [];
        foreach ($definition['steps'] ?? [] as $step) {
            $stepVisible = $state['steps'][$step['key']]['visible'] ?? false;
            if (! empty($step['repeatable'])) {
                if ($stepVisible && self::isList($raw[$step['key']] ?? null)) {
                    $out[$step['key']] = array_slice($raw[$step['key']], 0, (int) (($step['max_repeats'] ?? 0) ?: 10));
                }

                continue;
            }
            foreach ($step['fields'] ?? [] as $field) {
                $fs = $state['fields'][$field['key']];
                if (! $stepVisible || ! $fs['visible']) {
                    continue;
                }
                $value = array_key_exists($field['key'], $raw) ? $raw[$field['key']] : null;
                $vo = $field['variant_option'] ?? null;
                if ($vo !== null && array_key_exists($vo, $state['variantSets'])) {
                    $value = $state['variantSets'][$vo];
                }
                if (($field['mode'] ?? null) === 'fixed') {
                    $value = $field['fixed_value'] ?? null;
                }
                $type = $field['type'] ?? '';
                $byStock = fn ($v) => isset($availability[$field['key']]) && ($availability[$field['key']][self::str($v)] ?? null) === false;
                if (in_array($type, self::CHOICE_TYPES, true)) {
                    if (! self::isEmpty($value) && (! self::optionUsable($fs, $value) || $byStock($value))) {
                        $value = null;
                    }
                } elseif (in_array($type, self::MULTI_TYPES, true)) {
                    $list = self::isList($value) ? $value : (self::isEmpty($value) ? null : [$value]);
                    $value = $list === null ? null : array_values(array_filter($list, fn ($v) => self::optionUsable($fs, $v)));
                    if ($value === []) {
                        $value = null;
                    }
                }
                if (self::isEmpty($value)) {
                    $def = $fs['default'];
                    if (self::isEmpty($def) && ! empty($field['options'])) {
                        foreach ($field['options'] as $o) {
                            if (! empty($o['default'])) {
                                $def = $o['value'];
                                break;
                            }
                        }
                    }
                    if (! self::isEmpty($def)) {
                        if (in_array($type, self::CHOICE_TYPES, true) && (! self::optionUsable($fs, $def) || $byStock($def))) {
                            $def = null;
                        }
                        if (in_array($type, self::MULTI_TYPES, true)) {
                            $def = array_values(array_filter(self::isList($def) ? $def : [$def], fn ($v) => self::optionUsable($fs, $v)));
                        }
                    }
                    $value = self::isEmpty($def) ? null : $def;
                }
                if (! self::isEmpty($value)) {
                    $out[$field['key']] = $value;
                }
            }
        }

        return $out;
    }

    private static function same(array $a, array $b): bool
    {
        return json_encode(self::sortKeys($a)) === json_encode(self::sortKeys($b));
    }

    private static function sortKeys(mixed $v): mixed
    {
        if (is_array($v)) {
            if (! array_is_list($v)) {
                ksort($v);
            }

            return array_map([self::class, 'sortKeys'], $v);
        }

        return $v;
    }

    /** @return list<array{step:array,field:array}> */
    private static function variantFields(array $definition): array
    {
        return array_values(array_filter(self::fieldsOf($definition), fn ($x) => ! empty($x['field']['variant_option'])));
    }

    private static function variantAvailability(array $definition, array $answers, ?array $variants): array
    {
        $out = [];
        $vfs = self::variantFields($definition);
        if (! $vfs || ! $variants) {
            return $out;
        }
        // Available = some in-stock variant has this value plus the picks for the variant options
        // earlier in the flow (like Shopify's pickers), so a sold-out size never clears the colour.
        foreach ($vfs as $index => ['field' => $field]) {
            $earlier = array_slice($vfs, 0, $index);
            $out[$field['key']] = [];
            foreach ($field['options'] ?? [] as $o) {
                $hit = false;
                foreach ($variants as $v) {
                    if (($v['available'] ?? true) === false || self::str($v['options'][$field['variant_option']] ?? null) !== self::str($o['value'])) {
                        continue;
                    }
                    $fits = true;
                    foreach ($earlier as ['field' => $g]) {
                        if (self::isEmpty($answers[$g['key']] ?? null)) {
                            continue;
                        }
                        if (self::str($v['options'][$g['variant_option']] ?? null) !== self::str($answers[$g['key']])) {
                            $fits = false;
                            break;
                        }
                    }
                    if ($fits) {
                        $hit = true;
                        break;
                    }
                }
                $out[$field['key']][self::str($o['value'])] = $hit;
            }
        }

        return $out;
    }

    /** @return array{0:bool,1:?array} */
    private static function resolveVariant(array $definition, array $answers, ?array $variants): array
    {
        $vfs = self::variantFields($definition);
        if (! $vfs || ! is_array($variants)) {
            return [false, null];
        }
        foreach ($vfs as ['field' => $f]) {
            if (self::isEmpty($answers[$f['key']] ?? null)) {
                return [true, null];
            }
        }
        foreach ($variants as $v) {
            $all = true;
            foreach ($vfs as ['field' => $f]) {
                if (self::str($v['options'][$f['variant_option']] ?? null) !== self::str($answers[$f['key']])) {
                    $all = false;
                    break;
                }
            }
            if ($all) {
                return [true, $v];
            }
        }

        return [true, null];
    }

    private static function validateField(array $field, array $fs, mixed $value, string $prefix, array &$errors): void
    {
        $key = $prefix.$field['key'];
        $v = $field['validation'] ?? [];
        $type = $field['type'] ?? '';
        if (self::isEmpty($value)) {
            if (! empty($fs['required'])) {
                $errors[] = ['field' => $key, 'code' => 'required'];
            }

            return;
        }
        if (in_array($type, self::CHOICE_TYPES, true)) {
            $found = false;
            foreach ($field['options'] ?? [] as $o) {
                if (self::str($o['value']) === self::str($value)) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                $errors[] = ['field' => $key, 'code' => 'invalid_option'];
            }
        }
        if ($type === 'text') {
            $len = mb_strlen(is_scalar($value) ? (string) $value : '', 'UTF-8');
            if (isset($v['max_length']) && $len > $v['max_length']) {
                $errors[] = ['field' => $key, 'code' => 'max_length'];
            }
            if (isset($v['min_length']) && $len < $v['min_length']) {
                $errors[] = ['field' => $key, 'code' => 'min_length'];
            }
            if (! empty($v['pattern'])) {
                $re = '~'.str_replace('~', '\~', (string) $v['pattern']).'~u';
                $ok = @preg_match($re, (string) $value);
                if ($ok === 0) {
                    $errors[] = ['field' => $key, 'code' => 'pattern'];
                }
            }
        }
        if ($type === 'number') {
            $n = self::num($value);
            if (isset($v['min']) && $n < $v['min']) {
                $errors[] = ['field' => $key, 'code' => 'min'];
            }
            if (isset($v['max']) && $n > $v['max']) {
                $errors[] = ['field' => $key, 'code' => 'max'];
            }
        }
        if ($type === 'dimensions') {
            $d = self::dims($value);
            foreach (['w', 'h'] as $side) {
                if (isset($v['min_'.$side]) && $d[$side] < $v['min_'.$side]) {
                    $errors[] = ['field' => $key, 'code' => 'min_'.$side];
                }
                if (isset($v['max_'.$side]) && $d[$side] > $v['max_'.$side]) {
                    $errors[] = ['field' => $key, 'code' => 'max_'.$side];
                }
            }
        }
        if (in_array($type, self::MULTI_TYPES, true)) {
            $n = self::isList($value) ? count($value) : 1;
            if (isset($v['min_choices']) && $n < $v['min_choices']) {
                $errors[] = ['field' => $key, 'code' => 'min_choices'];
            }
            if (isset($v['max_choices']) && $n > $v['max_choices']) {
                $errors[] = ['field' => $key, 'code' => 'max_choices'];
            }
        }
    }

    private static function formulaVars(array $definition, array $answers, array $extra): array
    {
        $vars = $definition['settings']['variables'] ?? [];
        foreach (self::fieldsOf($definition) as ['field' => $field]) {
            $value = $answers[$field['key']] ?? null;
            $type = $field['type'] ?? '';
            $n = 0.0;
            if ($type === 'number') {
                $n = self::num($value);
            } elseif ($type === 'dimensions') {
                $d = self::dims($value);
                $n = $d['area'];
                $vars[$field['key'].'_w'] = $d['w'];
                $vars[$field['key'].'_h'] = $d['h'];
            } elseif ($type === 'text') {
                $n = (float) self::charCount($value, true);
            } elseif (in_array($type, self::MULTI_TYPES, true)) {
                $n = (float) (self::isList($value) ? count($value) : 0);
            } elseif (in_array($type, self::CHOICE_TYPES, true)) {
                $n = self::num($value);
            }
            $vars[$field['key']] = $n;
            $selected = array_map([self::class, 'str'], self::isList($value) ? $value : (self::isEmpty($value) ? [] : [$value]));
            foreach ($field['options'] ?? [] as $o) {
                if (in_array(self::str($o['value']), $selected, true)) {
                    $vars = array_merge($vars, $o['vars'] ?? []);
                }
            }
        }

        return array_merge($vars, $extra);
    }

    /** @return array{cents:int,percent:float} */
    private static function ruleAmount(?array $rule, ?array $field, mixed $value, array $ctx, array &$errors): array
    {
        $none = ['cents' => 0, 'percent' => 0.0];
        if (! $rule || empty($rule['type'])) {
            return $none;
        }
        switch ($rule['type']) {
            case 'fixed':
                return ['cents' => self::cents($rule['amount'] ?? 0), 'percent' => 0.0];
            case 'percent':
                return ['cents' => 0, 'percent' => self::num($rule['percent'] ?? 0)];
            case 'per_unit':
                $units = ($rule['basis'] ?? null) === 'area' ? self::dims($value)['area'] : self::num($value);
                $divisor = self::num($rule['divisor'] ?? 0) ?: 1.0;

                return ['cents' => self::cents(self::num($rule['amount'] ?? 0) * $units / $divisor), 'percent' => 0.0];
            case 'per_char':
                return ['cents' => self::cents(self::num($rule['amount'] ?? 0) * self::charCount($value, ! empty($rule['count_spaces']))), 'percent' => 0.0];
            case 'tiered':
                $byValue = ($rule['basis'] ?? null) === 'value';
                $n = $byValue ? self::num($value) : (float) $ctx['quantity'];
                foreach ($rule['tiers'] ?? [] as $t) {
                    if ($n >= self::num($t['min'] ?? 0) && (! isset($t['max']) || $n <= self::num($t['max']))) {
                        return ['cents' => self::cents(self::num($t['amount'] ?? 0) * ($byValue ? $n : 1)), 'percent' => 0.0];
                    }
                }

                return $none;
            case 'table':
                $table = $ctx['definition']['tables'][$rule['table'] ?? ''] ?? null;
                $row = $ctx['answers'][$rule['row_field'] ?? ($table['row_field'] ?? '')] ?? null;
                $col = $ctx['answers'][$rule['col_field'] ?? ($table['col_field'] ?? '')] ?? null;
                $cell = $table['values'][self::str($row)][self::str($col)] ?? null;
                if ($cell === null) {
                    $errors[] = ['field' => $field['key'] ?? null, 'code' => 'price_table_missing'];

                    return $none;
                }

                return ['cents' => self::cents($cell), 'percent' => 0.0];
            case 'formula':
                try {
                    return ['cents' => self::cents(Formula::evaluate((string) ($rule['expression'] ?? ''), $ctx['vars'])), 'percent' => 0.0];
                } catch (FormulaError) {
                    $errors[] = ['field' => $field['key'] ?? null, 'code' => 'formula_error'];

                    return $none;
                }
        }

        return $none;
    }

    private static function applyRounding(int $unit, ?array $rounding): int
    {
        $mode = $rounding['mode'] ?? 'none';
        if ($mode === 'none') {
            return $unit;
        }
        if ($mode === 'ending') {
            $end = self::cents($rounding['ending'] ?? 0.99);
            $v = (int) floor($unit / 100) * 100 + $end;

            return $v < $unit ? $v + 100 : $v;
        }
        $step = max(1, self::cents($rounding['step'] ?? 1));

        return match ($mode) {
            'up' => (int) ceil($unit / $step) * $step,
            'down' => (int) floor($unit / $step) * $step,
            default => (int) floor($unit / $step + 0.5) * $step,
        };
    }

    private static function priceOf(array $definition, array $answers, array $state, int $base, int $quantity, array $ctx, array &$errors): array
    {
        $lines = [['label' => 'Base', 'cents' => $base]];
        $options = 0;
        $percent = 0.0;
        $components = [];
        $priceCtx = ['definition' => $definition, 'answers' => $answers, 'quantity' => $quantity];
        $priceCtx['vars'] = self::formulaVars($definition, $answers, ['base' => $base / 100, 'quantity' => $quantity, 'options' => 0, 'subtotal' => 0]);

        $priceField = function (array $field, mixed $value, string $label, array $scope) use (&$options, &$percent, &$lines, &$components, &$errors, $priceCtx): void {
            $add = function (array $r, string $what) use (&$options, &$percent, &$lines): void {
                if ($r['cents']) {
                    $options += $r['cents'];
                    $lines[] = ['label' => $what, 'cents' => $r['cents']];
                }
                if ($r['percent']) {
                    $percent += $r['percent'];
                    $lines[] = ['label' => $what, 'percent' => $r['percent']];
                }
            };
            $scoped = ['answers' => $scope] + $priceCtx;
            if (! empty($field['price_rule'])) {
                $add(self::ruleAmount($field['price_rule'], $field, $value, $scoped, $errors), $label);
            }
            $selected = array_map([self::class, 'str'], self::isList($value) ? $value : (self::isEmpty($value) ? [] : [$value]));
            foreach ($field['options'] ?? [] as $o) {
                if (! in_array(self::str($o['value']), $selected, true)) {
                    continue;
                }
                $r = self::ruleAmount($o['price_rule'] ?? null, $field, $value, $scoped, $errors);
                $add($r, $label.' · '.self::str($o['label'] ?? $o['value']));
                if (! empty($o['linked_variant_id'])) {
                    $components[] = ['variant_id' => $o['linked_variant_id'], 'cents' => $r['cents'], 'quantity' => 1];
                }
            }
        };

        foreach ($definition['steps'] ?? [] as $step) {
            if (! ($state['steps'][$step['key']]['visible'] ?? false)) {
                continue;
            }
            if (! empty($step['repeatable'])) {
                foreach ($answers[$step['key']] ?? [] as $i => $item) {
                    foreach ($step['fields'] ?? [] as $field) {
                        if (! self::isEmpty($item[$field['key']] ?? null)) {
                            $priceField($field, $item[$field['key']], (($field['label'] ?? '') ?: $field['key']).' #'.($i + 1), $item);
                        }
                    }
                }

                continue;
            }
            foreach ($step['fields'] ?? [] as $field) {
                if (! $state['fields'][$field['key']]['visible'] || self::isEmpty($answers[$field['key']] ?? null)) {
                    continue;
                }
                if (! empty($field['variant_option'])) {
                    continue;
                }
                $priceField($field, $answers[$field['key']], (string) (($field['label'] ?? '') ?: $field['key']), $answers);
            }
        }

        foreach ($definition['price_rules'] ?? [] as $rule) {
            if (! self::matches($rule['when'] ?? null, $answers, $ctx)) {
                continue;
            }
            $r = self::ruleAmount($rule, null, null, $priceCtx, $errors);
            $label = ($rule['label'] ?? '') ?: 'Price rule';
            if ($r['cents']) {
                $options += $r['cents'];
                $lines[] = ['label' => $label, 'cents' => $r['cents']];
            }
            if ($r['percent']) {
                $percent += $r['percent'];
                $lines[] = ['label' => $label, 'percent' => $r['percent']];
            }
        }
        foreach ($state['priceAdds'] as $add) {
            if ($add['percent'] !== null) {
                $p = self::num($add['percent']);
                $percent += $p;
                $lines[] = ['label' => $add['label'], 'percent' => $p];
            } else {
                $c = self::cents($add['amount']);
                $options += $c;
                $lines[] = ['label' => $add['label'], 'cents' => $c];
            }
        }

        $markup = self::num($definition['settings']['markup_percent'] ?? 0) + $percent;
        $subtotal = (int) Formula::roundHalfUp(($base + $options) * (1 + $markup / 100));
        $formulaCents = 0;
        if (! empty($definition['settings']['formula'])) {
            try {
                $vars = array_merge($priceCtx['vars'], ['base' => $base / 100, 'options' => $options / 100, 'subtotal' => $subtotal / 100, 'quantity' => $quantity]);
                $formulaCents = self::cents(Formula::evaluate((string) $definition['settings']['formula'], $vars));
                if ($formulaCents) {
                    $lines[] = ['label' => ($definition['settings']['formula_label'] ?? '') ?: 'Formula', 'cents' => $formulaCents];
                }
            } catch (FormulaError) {
                $errors[] = ['field' => null, 'code' => 'formula_error'];
            }
        }
        $unit = max(0, self::applyRounding($subtotal + $formulaCents, $definition['settings']['rounding'] ?? null));

        return [
            'base_cents' => $base,
            'options_cents' => $options,
            'markup_percent' => $markup,
            'formula_cents' => $formulaCents,
            'unit_cents' => $unit,
            'total_cents' => $unit * $quantity,
            'lines' => $lines,
            'components' => $components,
        ];
    }

    private static function layersOf(array $definition, array $answers, array $state): array
    {
        $out = [];
        foreach ($definition['layers'] ?? [] as $l) {
            if (! self::matches($l['when'] ?? null, $answers)) {
                continue;
            }
            if (($l['type'] ?? 'image') === 'text') {
                $text = self::str($answers[$l['field'] ?? ''] ?? '');
                if ($text !== '') {
                    $out[] = ['id' => $l['id'], 'z' => $l['z'] ?? 0, 'type' => 'text', 'text' => $text, 'style' => $l['style'] ?? []];
                }

                continue;
            }
            if (array_key_exists($l['id'], $state['layers'])) {
                $image = $state['layers'][$l['id']];
            } elseif (! empty($l['field'])) {
                $image = $l['images'][self::str($answers[$l['field']] ?? null)] ?? ($l['image'] ?? null);
            } else {
                $image = $l['image'] ?? null;
            }
            if ($image) {
                $out[] = ['id' => $l['id'], 'z' => $l['z'] ?? 0, 'type' => 'image', 'image' => $image];
            }
        }
        usort($out, fn ($a, $b) => $a['z'] <=> $b['z']);

        return $out;
    }
}
