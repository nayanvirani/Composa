<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ConfiguratorVersion extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'definition' => 'array',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Published and archived versions are immutable: carts and orders point at them.
        static::updating(function (self $version) {
            if ($version->getOriginal('status') !== 'draft' && $version->isDirty('definition')) {
                throw new LogicException('A published configurator version cannot be edited; edit the draft instead.');
            }
        });
    }

    public function configurator(): BelongsTo
    {
        return $this->belongsTo(Configurator::class);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }
}
