<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A configurator: metadata plus versions. The published version is immutable; editing always
 * happens on the draft version, which becomes the next published version.
 */
class Configurator extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'display' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ConfiguratorVersion::class)->orderByDesc('version');
    }

    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(ConfiguratorVersion::class, 'published_version_id');
    }

    public function draftVersion(): BelongsTo
    {
        return $this->belongsTo(ConfiguratorVersion::class, 'draft_version_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class)->orderBy('priority');
    }

    public function isPublished(): bool
    {
        return $this->published_version_id !== null && $this->status === 'published';
    }

    public function hasUnpublishedChanges(): bool
    {
        return $this->draft_version_id !== null && $this->draft_version_id !== $this->published_version_id;
    }

    public function displaySetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->display ?? [], $key, $default);
    }
}
