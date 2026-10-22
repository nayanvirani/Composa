<?php

use App\Jobs\PurgeUninstalledShops;
use App\Services\PlanEnforcer;
use Illuminate\Support\Facades\Schedule;

// Run by the Railway "scheduler" service: php artisan schedule:work

// Shops are deleted 48 hours after uninstall.
Schedule::job(new PurgeUninstalledShops)->hourly()->name('purge-uninstalled-shops')->withoutOverlapping();

// After a downgrade grace period, extra published configurators move back to draft.
Schedule::call(fn () => app(PlanEnforcer::class)->enforceExpiredGrace())->dailyAt('03:10')->name('enforce-downgrades');

// Horizon queue metrics.
Schedule::command('horizon:snapshot')->everyFiveMinutes();
