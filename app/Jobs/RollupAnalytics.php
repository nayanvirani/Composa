<?php

namespace App\Jobs;

use App\Models\AnalyticsDaily;
use App\Models\AnalyticsEvent;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Nightly: rolls raw storefront events for one day into analytics_daily (opens, add to carts,
 * orders, revenue, sessions per step, choice counts, p75 load time). Safe to re-run for a day.
 * Raw events older than the retention window are deleted afterwards.
 */
class RollupAnalytics implements ShouldQueue
{
    use Queueable;

    public const RETENTION_DAYS = 90;

    public function __construct(public ?string $date = null) {}

    public function handle(): void
    {
        $day = CarbonImmutable::parse($this->date ?? now()->subDay()->toDateString())->startOfDay();

        AnalyticsEvent::query()
            ->whereNotNull('configurator_id')
            ->whereBetween('occurred_at', [$day, $day->endOfDay()])
            ->orderBy('id')
            ->get(['shop_id', 'configurator_id', 'session_id', 'type', 'step_key', 'value', 'meta'])
            ->groupBy('configurator_id')
            ->each(function ($events, $configuratorId) use ($day) {
                $byType = $events->groupBy('type');
                $steps = $byType->get('step', collect())->groupBy('step_key')
                    ->map(fn ($e) => $e->pluck('session_id')->filter()->unique()->count() ?: $e->count());
                $choices = [];
                foreach ($byType->get('choice', collect()) as $e) {
                    $value = $e->meta['value'] ?? null;
                    if ($e->step_key !== null && $value !== null) {
                        $choices[$e->step_key][$value] = ($choices[$e->step_key][$value] ?? 0) + 1;
                    }
                }

                AnalyticsDaily::query()->updateOrCreate(
                    ['configurator_id' => $configuratorId, 'date' => $day->toDateString()],
                    [
                        'shop_id' => $events->first()->shop_id,
                        'opens' => $byType->get('open', collect())->count(),
                        'add_to_carts' => $byType->get('add_to_cart', collect())->count(),
                        'orders' => $byType->get('order', collect())->count(),
                        'revenue_cents' => (int) $byType->get('order', collect())->sum('value'),
                        'steps' => $steps->all(),
                        'choices' => $choices,
                        'load_ms_p75' => self::percentile($byType->get('load_ms', collect())->pluck('value')->filter()->all(), 75),
                    ],
                );
            });

        AnalyticsEvent::query()->where('occurred_at', '<', now()->subDays(self::RETENTION_DAYS))->delete();
    }

    /** Nearest-rank percentile; null when there are no samples. */
    public static function percentile(array $values, int $p): ?int
    {
        if (! $values) {
            return null;
        }
        sort($values);

        return (int) $values[max(0, (int) ceil($p / 100 * count($values)) - 1)];
    }
}
