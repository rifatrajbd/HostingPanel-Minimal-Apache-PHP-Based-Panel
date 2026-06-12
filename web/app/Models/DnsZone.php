<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DnsZone extends Model
{
    protected $fillable = ['domain'];

    public function records(): HasMany
    {
        return $this->hasMany(DnsRecord::class);
    }

    /** Push the full record set to PowerDNS via panelctl. */
    public function sync(): \App\Services\CtlResult
    {
        $records = $this->records()
            ->get(['type', 'name', 'content', 'ttl', 'prio'])
            ->map(fn ($r) => [
                'type' => $r->type,
                'name' => $r->name,
                'content' => $r->content,
                'ttl' => $r->ttl,
                'prio' => $r->prio,
            ])->all();

        return app(\App\Services\PanelCtl::class)->run(
            'dns:sync',
            ['domain' => $this->domain],
            json_encode($records),
        );
    }
}
