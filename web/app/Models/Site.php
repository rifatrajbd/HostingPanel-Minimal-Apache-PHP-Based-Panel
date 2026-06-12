<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    protected $fillable = [
        'domain', 'php_version', 'doc_root', 'system_user',
        'ssl_enabled', 'cf_only', 'ini', 'ip_mode', 'aliases',
    ];

    protected $casts = [
        'ssl_enabled' => 'boolean',
        'cf_only' => 'boolean',
        'ini' => 'array',
        'aliases' => 'array',
    ];

    public function cronJobs(): HasMany
    {
        return $this->hasMany(CronJob::class);
    }
}
