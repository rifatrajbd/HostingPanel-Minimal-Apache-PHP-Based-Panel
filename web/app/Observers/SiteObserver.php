<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\Site;
use App\Services\PanelCtl;
use Filament\Notifications\Notification;

class SiteObserver
{
    public function deleting(Site $site): bool
    {
        $result = app(PanelCtl::class)->run('site:delete', ['domain' => $site->domain]);

        if (!$result->ok()) {
            Notification::make()
                ->title('site:delete failed')
                ->body($result->output())
                ->danger()
                ->persistent()
                ->send();
            return false; // abort the DB delete
        }

        AuditLog::record('site.delete', $site->domain);
        return true;
    }
}
