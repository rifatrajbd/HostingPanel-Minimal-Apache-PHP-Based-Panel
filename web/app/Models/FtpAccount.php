<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FtpAccount extends Model
{
    protected $fillable = ['username', 'site_id'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
