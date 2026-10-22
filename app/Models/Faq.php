<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A FAQ shown on the website (home, pricing, help) or in the app. */
class Faq extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'placements' => 'array',
        ];
    }
}
