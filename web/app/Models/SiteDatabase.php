<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SiteDatabase extends Model
{
    protected $fillable = ['name', 'db_user'];

    public function users(): HasMany
    {
        return $this->hasMany(DatabaseUser::class);
    }
}
