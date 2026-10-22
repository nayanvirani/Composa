<?php

namespace App\Support;

use App\Models\Shop;
use App\Models\SystemEvent;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Monitoring signals for super admin: price mismatches, signature failures, webhook failures,
 * Cart Transform conflicts, slow storefront loads and failed jobs. Repeats of the same signal
 * increase a counter instead of creating new rows.
 */
final class Monitor
{
    public const PRICE_MISMATCH = 'price_mismatch';

    public const SIGNATURE_FAILED = 'signature_failed';

    public const WEBHOOK_FAILED = 'webhook_failed';

    public const SLOW_LOAD = 'slow_load';

    public const CART_TRANSFORM_CONFLICT = 'cart_transform_conflict';

    public const JOB_FAILED = 'job_failed';

    public const FUNCTION_FAILED = 'function_failed';

    public static function record(string $type, string $message, ?Shop $shop = null, string $severity = 'warning', array $context = []): void
    {
        try {
            $fingerprint = hash('sha256', $type.'|'.($shop?->id ?? '-').'|'.$message);
            $event = SystemEvent::query()->firstOrNew(['fingerprint' => $fingerprint]);
            if ($event->exists) {
                $event->count++;
                $event->last_seen_at = now();
                $event->resolved_at = null;
                $event->context = $context ?: $event->context;
            } else {
                $event->fill([
                    'shop_id' => $shop?->id, 'type' => $type, 'severity' => $severity, 'message' => mb_substr($message, 0, 250),
                    'context' => $context ?: null, 'count' => 1, 'first_seen_at' => now(), 'last_seen_at' => now(),
                ]);
            }
            $event->save();
        } catch (Throwable $e) {
            Log::warning('Monitor could not record '.$type.': '.$e->getMessage());
        }
    }
}
