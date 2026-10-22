<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A transactional email with {{token}} placeholders. */
class EmailTemplate extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tokens' => 'array',
            'active' => 'boolean',
        ];
    }
}
