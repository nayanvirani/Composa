<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A feature flag: off, on, or targeted at plans and shops. */
class FeatureFlag extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'plans' => 'array',
            'shop_ids' => 'array',
        ];
    }
}
