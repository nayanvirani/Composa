<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A raw storefront event (open, step, add_to_cart, load_ms), rolled up nightly into analytics_daily. */
class AnalyticsEvent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function configurator(): BelongsTo
    {
        return $this->belongsTo(Configurator::class);
    }
}
