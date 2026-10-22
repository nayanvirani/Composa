<?php

namespace App\Jobs;

use App\Models\Shop;
use App\Services\StorefrontPublisher;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Republishes a shop's storefront metafields after publishing, assignment or settings changes. */
class SyncStorefrontMetafields implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $uniqueFor = 30;

    public function __construct(public int $shopId) {}

    public function uniqueId(): string
    {
        return (string) $this->shopId;
    }

    public function backoff(): array
    {
        return [10, 30, 120, 600];
    }

    public function handle(StorefrontPublisher $publisher): void
    {
        $shop = Shop::query()->find($this->shopId);
        if ($shop && $shop->isActive()) {
            $publisher->publish($shop);
        }
    }
}
