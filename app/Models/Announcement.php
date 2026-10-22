<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A banner on the website (scope site) or in the merchant app (scope app). */
class Announcement extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'audience' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'active' => 'boolean',
        ];
    }
}
