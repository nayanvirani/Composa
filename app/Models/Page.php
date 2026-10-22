<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A website page built from ordered section blocks ({type, visible, data}), one row per locale.
 * The public site renders each visible block with resources/views/site/sections/{type}.blade.php.
 */
class Page extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['sections' => 'array', 'seo' => 'array', 'publish_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        // Every content change keeps a snapshot for the page history (last 50 per page).
        static::saved(function (Page $page) {
            if (! $page->wasChanged(['title', 'sections', 'seo', 'status']) && ! $page->wasRecentlyCreated) {
                return;
            }
            $page->revisions()->create(['user_id' => auth()->id(), 'snapshot' => $page->only(['title', 'sections', 'seo', 'status', 'template'])]);
            $keep = $page->revisions()->limit(50)->pluck('id');
            PageRevision::query()->where('page_id', $page->id)->whereNotIn('id', $keep)->delete();
        });
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PageRevision::class)->latest('created_at');
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', 'published')->where(fn ($q) => $q->whereNull('publish_at')->orWhere('publish_at', '<=', now()));
    }

    /** @return list<array{type:string,visible:bool,data:array}> */
    public function visibleSections(): array
    {
        return array_values(array_filter($this->sections ?? [], fn ($s) => ($s['visible'] ?? true) && ! ($s['data']['hidden'] ?? false) && ! empty($s['type'])));
    }

    public function section(string $type): ?array
    {
        foreach ($this->sections ?? [] as $s) {
            if (($s['type'] ?? null) === $type) {
                return $s['data'] ?? [];
            }
        }

        return null;
    }
}
