<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MailDomain extends Model
{
    protected $fillable = ['domain', 'dkim_selector', 'dkim_dns'];

    public function mailboxes(): HasMany
    {
        return $this->hasMany(Mailbox::class);
    }
}
