<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A versioned template definition with its rollout target. */
class TemplateVersion extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'definition' => 'array',
            'rollout_value' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }
}
