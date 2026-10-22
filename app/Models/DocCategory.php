<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A help-center category. */
class DocCategory extends Model
{
    protected $guarded = ['id'];

    public function articles(): HasMany
    {
        return $this->hasMany(DocArticle::class);
    }
}
