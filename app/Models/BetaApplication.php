<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A beta program application from the website. */
class BetaApplication extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'categories' => 'array',
        ];
    }
}
