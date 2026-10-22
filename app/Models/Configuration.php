<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One shopper's answers, frozen with the signed price when added to cart. The id is the _config_id line property. */
class Configuration extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'result' => 'array',
            'summary' => 'array',
            'expires_at' => 'datetime',
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

    public function version(): BelongsTo
    {
        return $this->belongsTo(ConfiguratorVersion::class, 'version_id');
    }

    public function uploads(): HasMany
    {
        return $this->hasMany(Upload::class, 'configuration_id');
    }
}
