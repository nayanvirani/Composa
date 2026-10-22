<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A changelog revision. */
class ChangelogEntry extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'released_at' => 'date',
        ];
    }
}
