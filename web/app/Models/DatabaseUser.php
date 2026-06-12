<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatabaseUser extends Model
{
    protected $fillable = ['site_database_id', 'username', 'privileges'];

    public function database(): BelongsTo
    {
        return $this->belongsTo(SiteDatabase::class, 'site_database_id');
    }
}
