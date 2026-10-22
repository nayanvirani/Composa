<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A website menu (header, footer columns). */
class NavigationMenu extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'items' => 'array',
        ];
    }
}
