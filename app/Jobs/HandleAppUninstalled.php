<?php

namespace App\Jobs;

use App\Models\Shop;
use App\Services\ShopInstaller;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Marks the shop uninstalled; its data is purged 48 hours later by PurgeUninstalledShops. */
class HandleAppUninstalled implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $shopId) {}

    public function handle(ShopInstaller $installer): void
    {
        if ($shop = Shop::query()->find($this->shopId)) {
            $installer->uninstalled($shop);
        }
    }
}
