<?php

use App\Jobs\CleanupStorefrontData;
use App\Jobs\PurgeUninstalledShops;
use App\Jobs\RollupAnalytics;
use App\Services\PlanEnforcer;
use Illuminate\Support\Facades\Schedule;

// Run by the Railway "scheduler" service: php artisan schedule:work

// Shops are deleted 48 hours after uninstall.
Schedule::job(new PurgeUninstalledShops)->hourly()->name('purge-uninstalled-shops')->withoutOverlapping();

// After a downgrade grace period, extra published configurators move back to draft.
Schedule::call(fn () => app(PlanEnforcer::class)->enforceExpiredGrace())->dailyAt('03:10')->name('enforce-downgrades');

// Yesterday's storefront events into analytics_daily.
Schedule::job(new RollupAnalytics)->dailyAt('01:15')->name('rollup-analytics')->withoutOverlapping();

// Expired configurations and abandoned shopper uploads.
Schedule::job(new CleanupStorefrontData)->dailyAt('02:30')->name('cleanup-storefront-data')->withoutOverlapping();

// Horizon queue metrics.
Schedule::command('horizon:snapshot')->everyFiveMinutes();
