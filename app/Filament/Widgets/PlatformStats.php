<?php

namespace App\Filament\Widgets;

use App\Models\Configurator;
use App\Models\Shop;
use App\Models\SupportTicket;
use App\Models\SystemEvent;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Headline numbers. MRR is estimated from plan prices; Shopify's payouts are the source of truth. */
class PlatformStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $active = Shop::query()->where('status', 'active');
        $installs = Shop::query()->where('installed_at', '>=', now()->subDays(30))->count();
        $uninstalls = Shop::query()->where('uninstalled_at', '>=', now()->subDays(30))->count();
        $paying = (clone $active)->whereHas('plan', fn ($q) => $q->where('price_cents', '>', 0))->whereIn('subscription_status', ['active', 'ACTIVE'])->count();
        $mrr = (int) (clone $active)->whereIn('subscription_status', ['active', 'ACTIVE'])->join('plans', 'plans.id', '=', 'shops.plan_id')->sum('plans.price_cents');
        $trend = collect(range(13, 0))->map(fn ($d) => Shop::query()->whereDate('installed_at', now()->subDays($d)->toDateString())->count())->all();

        $stats = [
            Stat::make('Active shops', number_format((clone $active)->count()))->description($installs.' installs · '.$uninstalls.' uninstalls (30 days)')->chart($trend)->color('primary'),
            Stat::make('Paying shops', number_format($paying)),
            Stat::make('Live configurators', number_format(Configurator::query()->where('status', 'published')->count())),
            Stat::make('Open tickets', number_format(SupportTicket::query()->where('status', 'open')->count()))->color('warning'),
            Stat::make('Critical issues', number_format(SystemEvent::query()->whereNull('resolved_at')->where('severity', 'critical')->count()))->color('danger'),
        ];
        if (auth()->user()?->can('view_finance')) {
            array_splice($stats, 2, 0, [Stat::make('Est. MRR', '$'.number_format($mrr / 100))->description('From plan prices; billed by Shopify')]);
        }

        return $stats;
    }
}
