<?php

namespace App\Services;

use App\Models\AnalyticsDaily;
use App\Models\AnalyticsEvent;
use App\Models\Configurator;
use App\Models\ConfiguredOrder;
use App\Models\Shop;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Merchant analytics: opens, step funnel, add-to-cart rate, most chosen options and revenue per
 * configurator. Reads the nightly rollups plus today's raw events.
 */
class AnalyticsReport
{
    public function summary(Shop $shop, int $days = 30, ?int $configuratorId = null): array
    {
        $from = now()->subDays($days)->startOfDay();
        $daily = AnalyticsDaily::query()->where('shop_id', $shop->id)->where('date', '>=', $from->toDateString())
            ->when($configuratorId, fn ($q) => $q->where('configurator_id', $configuratorId))->get();
        $today = $this->todayCounts($shop, $configuratorId);

        $opens = (int) $daily->sum('opens') + $today['opens'];
        $carts = (int) $daily->sum('add_to_carts') + $today['add_to_carts'];
        $orders = ConfiguredOrder::query()->where('shop_id', $shop->id)->where('placed_at', '>=', $from)
            ->when($configuratorId, fn ($q) => $q->where('configurator_id', $configuratorId));
        $revenue = (int) (clone $orders)->sum('line_total_cents');
        $orderCount = (clone $orders)->count();

        $steps = [];
        foreach ($daily as $row) {
            foreach ($row->steps ?? [] as $step => $n) {
                $steps[$step] = ($steps[$step] ?? 0) + $n;
            }
        }
        $choices = [];
        foreach ($daily as $row) {
            foreach ($row->choices ?? [] as $field => $values) {
                foreach ($values as $value => $n) {
                    $choices[$field][$value] = ($choices[$field][$value] ?? 0) + $n;
                }
            }
        }

        $previousOpens = (int) AnalyticsDaily::query()->where('shop_id', $shop->id)
            ->whereBetween('date', [$from->copy()->subDays($days)->toDateString(), $from->copy()->subDay()->toDateString()])
            ->when($configuratorId, fn ($q) => $q->where('configurator_id', $configuratorId))->sum('opens');

        return [
            'range_days' => $days,
            'opens' => $opens,
            'opens_previous' => $previousOpens,
            'add_to_carts' => $carts,
            'cart_rate' => $opens ? round($carts * 100 / $opens, 1) : 0,
            'orders' => $orderCount,
            'revenue_cents' => $revenue,
            'aov_cents' => $orderCount ? intdiv($revenue, $orderCount) : 0,
            'funnel' => $this->funnel($shop, $configuratorId, $opens, $steps, $carts),
            'choices' => $this->topChoices($choices),
            'by_configurator' => $this->byConfigurator($shop, $from),
            'series' => $daily->groupBy(fn ($r) => $r->date->toDateString())->map(fn ($rows, $date) => ['date' => $date, 'opens' => $rows->sum('opens'), 'add_to_carts' => $rows->sum('add_to_carts')])->values()->all(),
        ];
    }

    private function funnel(Shop $shop, ?int $configuratorId, int $opens, array $steps, int $carts): array
    {
        $configurator = $configuratorId ? Configurator::query()->find($configuratorId) : $shop->configurators()->where('status', 'published')->first();
        $definition = $configurator?->publishedVersion?->definition ?? [];
        $rows = [['step' => 'Opened', 'count' => $opens]];
        foreach ($definition['steps'] ?? [] as $s) {
            $rows[] = ['step' => $s['title'] ?? $s['key'], 'count' => (int) ($steps[$s['key']] ?? 0)];
        }
        $rows[] = ['step' => 'Add to cart', 'count' => $carts];

        return array_map(fn ($r) => $r + ['percent' => $opens ? round($r['count'] * 100 / $opens, 1) : 0], $rows);
    }

    private function topChoices(array $choices): array
    {
        $out = [];
        foreach ($choices as $field => $values) {
            arsort($values);
            $total = array_sum($values) ?: 1;
            $out[] = ['field' => $field, 'options' => array_map(fn ($v, $n) => ['value' => $v, 'percent' => round($n * 100 / $total)], array_keys(array_slice($values, 0, 4, true)), array_slice($values, 0, 4))];
        }

        return array_slice($out, 0, 6);
    }

    private function byConfigurator(Shop $shop, Carbon $from): array
    {
        $daily = AnalyticsDaily::query()->where('shop_id', $shop->id)->where('date', '>=', $from->toDateString())
            ->select('configurator_id', DB::raw('sum(opens) as opens'), DB::raw('sum(add_to_carts) as carts'))->groupBy('configurator_id')->get()->keyBy('configurator_id');
        $orders = ConfiguredOrder::query()->where('shop_id', $shop->id)->where('placed_at', '>=', $from)
            ->select('configurator_id', DB::raw('count(*) as orders'), DB::raw('sum(line_total_cents) as revenue'))->groupBy('configurator_id')->get()->keyBy('configurator_id');

        return $shop->configurators()->get(['id', 'name'])->map(function ($c) use ($daily, $orders) {
            $d = $daily[$c->id] ?? null;
            $o = $orders[$c->id] ?? null;
            $opens = (int) ($d->opens ?? 0);
            $count = (int) ($o->orders ?? 0);

            return ['id' => $c->id, 'name' => $c->name, 'opens' => $opens, 'cart_rate' => $opens ? round(($d->carts ?? 0) * 100 / $opens, 1) : 0, 'orders' => $count, 'revenue_cents' => (int) ($o->revenue ?? 0), 'aov_cents' => $count ? intdiv((int) $o->revenue, $count) : 0];
        })->all();
    }

    private function todayCounts(Shop $shop, ?int $configuratorId): array
    {
        $q = AnalyticsEvent::query()->where('shop_id', $shop->id)->where('occurred_at', '>=', now()->startOfDay())->when($configuratorId, fn ($q) => $q->where('configurator_id', $configuratorId));

        return ['opens' => (clone $q)->where('type', 'open')->count(), 'add_to_carts' => (clone $q)->where('type', 'add_to_cart')->count()];
    }
}
