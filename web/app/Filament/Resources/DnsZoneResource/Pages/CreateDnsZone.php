<?php

namespace App\Filament\Resources\DnsZoneResource\Pages;

use App\Filament\Resources\DnsZoneResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDnsZone extends CreateRecord
{
    protected static string $resource = DnsZoneResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['domain'] = strtolower($data['domain']);
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        // Straight to record management after the zone (SOA/NS) is created.
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
