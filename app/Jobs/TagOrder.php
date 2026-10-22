<?php

namespace App\Jobs;

use App\Models\Shop;
use App\Shopify\AdminApi;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class TagOrder implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public int $shopId, public string $orderId, public array $tags) {}

    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(): void
    {
        $shop = Shop::query()->find($this->shopId);
        if (! $shop || ! $shop->isActive() || ! $this->tags) {
            return;
        }
        $id = str_starts_with($this->orderId, 'gid://') ? $this->orderId : 'gid://shopify/Order/'.$this->orderId;
        AdminApi::for($shop)->mutate('mutation($id: ID!, $tags: [String!]!) { tagsAdd(id: $id, tags: $tags) { node { id } userErrors { field message } } }', ['id' => $id, 'tags' => $this->tags], 'tagsAdd');
    }
}
