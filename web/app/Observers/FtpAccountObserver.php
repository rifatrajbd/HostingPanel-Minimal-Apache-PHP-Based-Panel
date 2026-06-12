<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\FtpAccount;
use App\Services\PanelCtl;
use Filament\Notifications\Notification;

class FtpAccountObserver
{
    public function deleting(FtpAccount $account): bool
    {
        $result = app(PanelCtl::class)->run('ftp:delete', ['username' => $account->username]);
        if (!$result->ok()) {
            Notification::make()->title('ftp:delete failed')->body($result->output())
                ->danger()->persistent()->send();
            return false;
        }
        AuditLog::record('ftp.delete', $account->username);
        return true;
    }
}
