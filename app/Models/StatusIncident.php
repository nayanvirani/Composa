<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An incident on the public status page. */
class StatusIncident extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'component_ids' => 'array',
            'started_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
