<?php

namespace App\Services;

use App\Models\Configuration;
use App\Models\ConfiguredOrder;
use App\Models\GdprRequest;
use App\Models\Shop;
use App\Models\Upload;
use Illuminate\Support\Facades\Storage;

/**
 * Shopify's mandatory compliance webhooks. Composa stores no customer accounts; personal data only
 * appears in configurations (free-text answers such as engraving), uploads and the customer name
 * kept on configured orders.
 */
class GdprProcessor
{
    public function process(GdprRequest $request): string
    {
        return match ($request->topic) {
            'customers/data_request' => $this->dataRequest($request),
            'customers/redact' => $this->redactCustomer($request),
            'shop/redact' => $this->redactShop($request),
            default => 'Unknown topic',
        };
    }

    private function orderIds(GdprRequest $request): array
    {
        return array_map(fn ($id) => (string) $id, $request->payload['orders_requested'] ?? $request->payload['orders_to_redact'] ?? []);
    }

    private function dataRequest(GdprRequest $request): string
    {
        $orders = ConfiguredOrder::query()->where('shop_id', $request->shop_id)->whereIn('order_id', $this->orderIds($request))->with('configuration')->get();
        $export = $orders->map(fn (ConfiguredOrder $o) => [
            'order' => $o->order_name ?? $o->order_id,
            'answers' => $o->configuration?->answers,
            'summary' => $o->configuration?->summary,
        ])->all();
        $path = 'gdpr/'.$request->shop_domain.'/data-request-'.$request->id.'.json';
        Storage::disk(config('composa.uploads.disk'))->put($path, json_encode($export, JSON_PRETTY_PRINT));

        return count($export).' configured order lines exported to '.$path.'; send to the merchant on request.';
    }

    private function redactCustomer(GdprRequest $request): string
    {
        $orders = ConfiguredOrder::query()->where('shop_id', $request->shop_id)->whereIn('order_id', $this->orderIds($request))->get();
        foreach ($orders as $order) {
            $order->forceFill(['customer_name' => null])->save();
            if ($order->configuration) {
                $this->scrub($order->configuration);
            }
        }

        return $orders->count().' configured order lines redacted.';
    }

    private function redactShop(GdprRequest $request): string
    {
        $shop = $request->shop ?? Shop::query()->where('domain', $request->shop_domain)->first();
        if (! $shop) {
            return 'Shop already removed.';
        }
        $this->deleteShopData($shop);

        return 'Shop data deleted.';
    }

    /** Removes free text and files from a configuration, keeping the price for the order record. */
    public function scrub(Configuration $configuration): void
    {
        $answers = collect($configuration->answers ?? [])->map(fn ($v) => is_string($v) && ! preg_match('/^[a-z0-9_.-]{1,40}$/i', $v) ? '[redacted]' : $v)->all();
        $configuration->forceFill(['answers' => $answers, 'summary' => null])->save();
        foreach ($configuration->uploads as $upload) {
            $this->deleteUpload($upload);
        }
    }

    public function deleteUpload(Upload $upload): void
    {
        Storage::disk(config('composa.uploads.disk'))->delete($upload->path);
        $upload->forceFill(['status' => 'deleted'])->save();
    }

    public function deleteShopData(Shop $shop): void
    {
        $disk = Storage::disk(config('composa.uploads.disk'));
        $disk->deleteDirectory('shops/'.$shop->id);
        $shop->delete();
    }
}
