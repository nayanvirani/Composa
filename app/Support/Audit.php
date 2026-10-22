<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Model;

/** Records super admin actions in the audit log. */
final class Audit
{
    public static function log(string $action, ?string $summary = null, ?Model $subject = null, ?Shop $shop = null, array $meta = []): void
    {
        AuditLog::query()->create([
            'user_id' => auth()->id(),
            'shop_id' => $shop?->id ?? ($subject instanceof Shop ? $subject->id : ($subject?->getAttribute('shop_id'))),
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'summary' => $summary,
            'meta' => $meta ?: null,
            'ip' => request()?->ip(),
        ]);
    }
}
