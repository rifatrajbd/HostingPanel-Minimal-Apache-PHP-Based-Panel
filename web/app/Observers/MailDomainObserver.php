<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\MailDomain;
use App\Services\PanelCtl;
use Filament\Notifications\Notification;

class MailDomainObserver
{
    public function deleting(MailDomain $domain): bool
    {
        $result = app(PanelCtl::class)->run('mail:domain:delete', ['domain' => $domain->domain]);

        if (!$result->ok()) {
            Notification::make()->title('mail:domain:delete failed')->body($result->output())
                ->danger()->persistent()->send();
            return false;
        }

        AuditLog::record('mail.domain.delete', $domain->domain);
        return true;
    }
}
