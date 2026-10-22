<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An order line that carries a configuration, linked from the orders/create webhook. */
class ConfiguredOrder extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'placed_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function configuration(): BelongsTo
    {
        return $this->belongsTo(Configuration::class);
    }

    public function configurator(): BelongsTo
    {
        return $this->belongsTo(Configurator::class);
    }
}
