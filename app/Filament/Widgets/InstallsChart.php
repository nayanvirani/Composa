<?php

namespace App\Filament\Widgets;

use App\Models\Shop;
use Filament\Widgets\ChartWidget;

class InstallsChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Installs and uninstalls — last 30 days';

    protected ?string $maxHeight = '260px';

    protected function getData(): array
    {
        $days = collect(range(29, 0))->map(fn ($d) => now()->subDays($d)->toDateString());
        $count = fn (string $column) => Shop::query()->where($column, '>=', now()->subDays(30)->startOfDay())
            ->selectRaw("date($column) as d, count(*) as c")->groupBy('d')->pluck('c', 'd');
        $in = $count('installed_at');
        $out = $count('uninstalled_at');

        return [
            'datasets' => [
                ['label' => 'Installs', 'data' => $days->map(fn ($d) => (int) ($in[$d] ?? 0))->all(), 'borderColor' => '#2f6f4f', 'backgroundColor' => 'rgba(47,111,79,.12)', 'fill' => true, 'tension' => 0.3],
                ['label' => 'Uninstalls', 'data' => $days->map(fn ($d) => (int) ($out[$d] ?? 0))->all(), 'borderColor' => '#b4532a', 'backgroundColor' => 'transparent', 'tension' => 0.3],
            ],
            'labels' => $days->map(fn ($d) => substr($d, 5))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
