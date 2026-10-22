<?php

namespace App\Jobs;

use App\Models\Shop;
use App\Services\CartTransformSetup;
use App\Services\ShopInstaller;
use App\Services\ThemeStatus;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** shop/update, themes/publish, locale changes: refresh details, cart mode and app embed status. */
class RefreshShopDetails implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 60;

    public function __construct(public int $shopId) {}

    public function uniqueId(): string
    {
        return (string) $this->shopId;
    }

    public function handle(ShopInstaller $installer, CartTransformSetup $cartTransform, ThemeStatus $theme): void
    {
        $shop = Shop::query()->find($this->shopId);
        if (! $shop || ! $shop->isActive()) {
            return;
        }
        $mode = $shop->cart_mode;
        $installer->refreshDetails($shop);
        if ($shop->cart_mode !== $mode) {
            $cartTransform->writeConfig($shop);
            SyncStorefrontMetafields::dispatch($shop->id);
        }
        $theme->check($shop);
    }
}
