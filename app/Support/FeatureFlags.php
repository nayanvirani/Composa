<?php

namespace App\Support;

use App\Models\FeatureFlag;
use App\Models\Shop;
use Illuminate\Support\Facades\Cache;

/** Feature flags: off, on for everyone, or targeted at plans and individual shops. */
final class FeatureFlags
{
    public static function enabled(string $key, ?Shop $shop = null): bool
    {
        $flag = Cache::remember('flag:'.$key, 60, fn () => FeatureFlag::query()->where('key', $key)->first());
        if (! $flag || $flag->state === 'off') {
            return false;
        }
        if ($flag->state === 'on') {
            return true;
        }
        if (! $shop) {
            return false;
        }

        return in_array($shop->id, array_map('intval', $flag->shop_ids ?? []), true)
            || ($shop->plan && in_array($shop->plan->handle, $flag->plans ?? [], true));
    }

    /** @return array<string,bool> every flag evaluated for a shop (shared with the merchant app) */
    public static function forShop(Shop $shop): array
    {
        return FeatureFlag::query()->pluck('key')->mapWithKeys(fn ($k) => [$k => self::enabled($k, $shop)])->all();
    }
}
