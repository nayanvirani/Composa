<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Shop;
use App\Models\Template;
use App\Support\FeatureFlags;

/** Global templates as a shop sees them: which are available and whether they fit its plan. */
class TemplateLibrary
{
    /** @return list<array<string,mixed>> */
    public function forShop(Shop $shop): array
    {
        $limits = PlanLimits::for($shop);
        $plans = Plan::query()->orderBy('sort')->get()->keyBy('handle');

        return Template::query()->where('status', 'published')->with('currentVersion')->orderByDesc('featured')->orderBy('sort')->get()
            ->filter(fn (Template $t) => $this->rolledOutTo($t, $shop))
            ->map(function (Template $t) use ($limits, $plans) {
                $definition = $t->currentVersion?->definition ?? [];
                $violations = $limits->violations($definition);
                $stats = PlanLimits::stats($definition);

                return [
                    'key' => $t->key,
                    'name' => $t->name,
                    'category' => $t->category,
                    'description' => $t->description,
                    'cover' => $t->cover_image,
                    'featured' => $t->featured,
                    'version' => $t->currentVersion?->version,
                    'steps' => array_map(fn ($s) => $s['title'] ?? $s['key'], $definition['steps'] ?? []),
                    'stats' => $stats,
                    'fits' => $violations === [],
                    'violations' => $violations,
                    'needs_plan' => $violations && $t->min_plan ? ($plans[$t->min_plan]->name ?? null) : null,
                ];
            })->values()->all();
    }

    public function definitionFor(Template $template): array
    {
        return $template->currentVersion?->definition ?? ConfiguratorService::blankDefinition();
    }

    private function rolledOutTo(Template $template, Shop $shop): bool
    {
        $version = $template->currentVersion;

        return match ($version?->rollout) {
            'plans' => in_array($shop->plan?->handle, (array) $version->rollout_value, true),
            'flag' => FeatureFlags::enabled((string) ($version->rollout_value[0] ?? ''), $shop),
            default => true,
        };
    }
}
