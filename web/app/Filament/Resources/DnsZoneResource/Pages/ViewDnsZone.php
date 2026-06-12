<?php

namespace App\Filament\Resources\DnsZoneResource\Pages;

use App\Filament\Resources\DnsZoneResource;
use Filament\Resources\Pages\ViewRecord;

class ViewDnsZone extends ViewRecord
{
    protected static string $resource = DnsZoneResource::class;

    public function getTitle(): string
    {
        return $this->record->domain . ' — DNS records';
    }
}
