<?php

namespace App\Jobs;

use App\Models\Shop;
use App\Services\GdprProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Hourly: deletes shops (and their files) 48 hours after uninstall, unless they reinstalled. */
class PurgeUninstalledShops implements ShouldQueue
{
    use Queueable;

    public function handle(GdprProcessor $processor): void
    {
        Shop::query()->where('status', 'uninstalled')->whereNotNull('purge_after')->where('purge_after', '<=', now())
            ->each(fn (Shop $shop) => $processor->deleteShopData($shop));
    }
}
