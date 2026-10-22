<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** A journal (blog) post. */
class Post extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['seo' => 'array', 'featured' => 'boolean', 'published_at' => 'datetime'];
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now());
    }
}
