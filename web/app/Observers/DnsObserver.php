<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\DnsRecord;
use App\Models\DnsZone;
use App\Services\PanelCtl;
use Filament\Notifications\Notification;

class DnsObserver
{
    public function zoneCreated(DnsZone $zone): void
    {
        $result = $zone->sync();
        AuditLog::record('dns.zone.add', $zone->domain);
        $this->notify($result, "Zone {$zone->domain} created");
    }

    public function zoneDeleting(DnsZone $zone): void
    {
        app(PanelCtl::class)->run('dns:zone:delete', ['domain' => $zone->domain]);
        AuditLog::record('dns.zone.delete', $zone->domain);
    }

    public function recordSaved(DnsRecord $record): void
    {
        $result = $record->zone?->sync();
        if ($result) {
            $this->notify($result, 'DNS records updated');
        }
    }

    public function recordDeleted(DnsRecord $record): void
    {
        $record->zone?->sync();
    }

    private function notify(\App\Services\CtlResult $result, string $title): void
    {
        if ($result->ok()) {
            Notification::make()->title($title)->success()->send();
        } else {
            Notification::make()->title('DNS sync failed')->body($result->output())
                ->danger()->persistent()->send();
        }
    }
}
