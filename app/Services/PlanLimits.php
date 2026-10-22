<?php

namespace App\Services;

use App\Engine\Engine;
use App\Models\Configurator;
use App\Models\Plan;
use App\Models\Shop;

/**
 * Plan limits and features. Limits come from the shop's plan, overridden per shop from super
 * admin. Hitting a limit pauses new publishes, assignments or uploads of that kind; it never takes
 * a live configurator offline or blocks checkout.
 */
class PlanLimits
{
    public function __construct(private readonly Shop $shop) {}

    public static function for(Shop $shop): self
    {
        return new self($shop);
    }

    public function plan(): ?Plan
    {
        return $this->shop->plan;
    }

    public function limit(string $key): ?int
    {
        $overrides = $this->shop->limit_overrides ?? [];
        if (array_key_exists($key, $overrides) && $overrides[$key] !== null && $overrides[$key] !== '') {
            return (int) $overrides[$key];
        }
        $plan = $this->plan();

        return $plan ? $plan->limit($key) : 0;
    }

    public function feature(string $key, mixed $default = null): mixed
    {
        return $this->plan()?->feature($key, $default) ?? $default;
    }

    /** @return array<string,int|float> */
    public function usage(): array
    {
        $configurators = $this->shop->configurators()->with(['draftVersion', 'publishedVersion'])->get();
        $steps = $options = $rules = 0;
        foreach ($configurators as $c) {
            foreach ([$c->draftVersion, $c->publishedVersion] as $v) {
                if (! $v) {
                    continue;
                }
                $stats = self::stats($v->definition);
                $steps = max($steps, $stats['steps']);
                $options = max($options, $stats['options']);
                $rules = max($rules, $stats['rules']);
            }
        }

        return [
            'configurators' => $configurators->where('status', 'published')->count(),
            'drafts' => $configurators->where('status', 'draft')->count(),
            'steps' => $steps,
            'options' => $options,
            'rules' => $rules,
            'products' => (int) $this->shop->assignments()->sum('product_count'),
            'storage_mb' => round($this->shop->uploads()->where('status', '!=', 'deleted')->sum('size') / 1048576, 1),
            'languages' => max(1, count($this->shop->locales ?? [$this->shop->primary_locale])),
        ];
    }

    /** @return array<string,array{used:int|float,limit:?int,ratio:float,state:string}> */
    public function meters(): array
    {
        $out = [];
        foreach ($this->usage() as $key => $used) {
            $limit = $this->limit($key);
            $ratio = $limit === null ? 0.0 : ($limit === 0 ? ($used > 0 ? 1.0 : 0.0) : $used / $limit);
            $out[$key] = ['used' => $used, 'limit' => $limit, 'ratio' => round($ratio, 3), 'state' => $ratio >= 1 ? 'reached' : ($ratio >= config('composa.limits.warn_at') ? 'warning' : 'ok')];
        }

        return $out;
    }

    /** Can one more of $key be added on top of current usage (e.g. publishing another configurator)? */
    public function allows(string $key, int|float $adding = 1): bool
    {
        $limit = $this->limit($key);

        return $limit === null || ($this->usage()[$key] ?? 0) + $adding <= $limit;
    }

    /** @return array{steps:int,options:int,rules:int,pricing_types:list<string>,nested:bool,repeatable:bool,uploads:bool,nested_rules:bool} */
    public static function stats(array $definition): array
    {
        $options = 0;
        $types = [];
        $uploads = $nested = $repeatable = false;
        foreach ($definition['steps'] ?? [] as $step) {
            $nested = $nested || ! empty($step['parent']);
            $repeatable = $repeatable || ! empty($step['repeatable']);
            foreach ($step['fields'] ?? [] as $field) {
                $options = max($options, count($field['options'] ?? []));
                $uploads = $uploads || ($field['type'] ?? '') === 'file';
                if (! empty($field['price_rule']['type'])) {
                    $types[] = $field['price_rule']['type'];
                }
                foreach ($field['options'] ?? [] as $o) {
                    if (! empty($o['price_rule']['type'])) {
                        $types[] = $o['price_rule']['type'];
                    }
                }
            }
        }
        foreach ($definition['price_rules'] ?? [] as $r) {
            $types[] = $r['type'] ?? 'fixed';
        }
        foreach ($definition['rules'] ?? [] as $r) {
            foreach ($r['actions'] ?? [] as $a) {
                if (($a['type'] ?? '') === 'add_price') {
                    $types[] = isset($a['percent']) ? 'percent' : 'fixed';
                }
            }
        }
        if (! empty($definition['settings']['formula'])) {
            $types[] = 'formula';
        }
        if (! empty($definition['settings']['markup_percent'])) {
            $types[] = 'percent';
        }
        $nestedRules = collect($definition['rules'] ?? [])->contains(fn ($r) => self::depth($r['conditions'] ?? null) > 1);

        return [
            'steps' => count($definition['steps'] ?? []),
            'options' => $options,
            'rules' => count($definition['rules'] ?? []),
            'pricing_types' => array_values(array_unique($types)),
            'nested' => $nested,
            'repeatable' => $repeatable,
            'uploads' => $uploads,
            'nested_rules' => $nestedRules,
        ];
    }

    private static function depth(?array $node): int
    {
        if (! $node) {
            return 0;
        }
        foreach (['all', 'any'] as $k) {
            if (isset($node[$k])) {
                return 1 + (int) collect($node[$k])->map(fn ($n) => self::depth($n))->max();
            }
        }

        return 0;
    }

    /**
     * Everything in a definition the shop's plan doesn't allow (checked before publishing).
     *
     * @return list<array{code:string,message:string,upgrade:bool}>
     */
    public function violations(array $definition, ?Configurator $configurator = null): array
    {
        $s = self::stats($definition);
        $out = [];
        $check = function (string $key, int $value, string $what) use (&$out) {
            $limit = $this->limit($key);
            if ($limit !== null && $value > $limit) {
                $out[] = ['code' => 'limit_'.$key, 'message' => "{$value} {$what}; your plan allows {$limit}.", 'upgrade' => true];
            }
        };
        $check('steps', $s['steps'], 'steps');
        $check('options', $s['options'], 'options in one field');
        $check('rules', $s['rules'], 'rules');

        foreach (array_diff($s['pricing_types'], $this->feature('pricing_types', [])) as $type) {
            $out[] = ['code' => 'pricing_'.$type, 'message' => str_replace('_', ' ', ucfirst($type)).' pricing is not included in your plan.', 'upgrade' => true];
        }
        if (($s['nested'] || $s['repeatable']) && ! $this->feature('nested_steps', false)) {
            $out[] = ['code' => 'nested_steps', 'message' => 'Nested and repeatable steps need the Growth plan or higher.', 'upgrade' => true];
        }
        if ($s['nested_rules'] && $this->feature('rules_level') !== 'advanced') {
            $out[] = ['code' => 'nested_rules', 'message' => 'Nested AND/OR rules need the Growth plan or higher.', 'upgrade' => true];
        }
        if ($s['uploads'] && ! $this->feature('uploads', false)) {
            $out[] = ['code' => 'uploads', 'message' => 'File upload fields need the Starter plan or higher.', 'upgrade' => true];
        }
        $translations = array_keys($definition['translations'] ?? []);
        $languages = $this->limit('languages');
        if ($languages !== null && count($translations) + 1 > $languages) {
            $out[] = ['code' => 'limit_languages', 'message' => (count($translations) + 1).' languages; your plan allows '.$languages.'.', 'upgrade' => true];
        }
        if ($configurator && ! $configurator->isPublished() && ! $this->allows('configurators')) {
            $out[] = ['code' => 'limit_configurators', 'message' => 'You’ve reached '.$this->limit('configurators').' published configurators. Unpublish one or upgrade.', 'upgrade' => true];
        }

        return $out;
    }

    /** Recomputes engine-level stats used by the builder UI. */
    public static function fieldCount(array $definition): int
    {
        return count(Engine::fieldsOf($definition));
    }
}
