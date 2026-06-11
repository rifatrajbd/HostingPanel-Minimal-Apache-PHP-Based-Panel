<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mailbox extends Model
{
    protected $fillable = ['mail_domain_id', 'address'];

    public function mailDomain(): BelongsTo
    {
        return $this->belongsTo(MailDomain::class);
    }
}
