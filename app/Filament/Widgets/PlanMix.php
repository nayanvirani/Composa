<?php

namespace App\Filament\Widgets;

use App\Models\Plan;
use Filament\Widgets\ChartWidget;

class PlanMix extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Active shops by plan';

    protected ?string $maxHeight = '260px';

    protected function getData(): array
    {
        $plans = Plan::query()->orderBy('sort')->withCount(['shops' => fn ($q) => $q->where('status', 'active')])->get();

        return [
            'datasets' => [['data' => $plans->pluck('shops_count')->all(), 'backgroundColor' => ['#c9c4b5', '#8a9a7b', '#2f6f4f', '#1d3b2d', '#b4532a']]],
            'labels' => $plans->pluck('name')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
