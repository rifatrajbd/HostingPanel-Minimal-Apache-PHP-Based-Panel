<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\SiteDatabase;
use App\Services\PanelCtl;
use Filament\Notifications\Notification;

class SiteDatabaseObserver
{
    public function deleting(SiteDatabase $db): bool
    {
        $result = app(PanelCtl::class)->run('db:delete', [
            'name' => $db->name,
            'user' => $db->db_user,
        ]);

        if (!$result->ok()) {
            Notification::make()->title('db:delete failed')->body($result->output())
                ->danger()->persistent()->send();
            return false;
        }

        // Drop any additional MySQL users attached to this database.
        $ctl = app(PanelCtl::class);
        foreach ($db->users as $extra) {
            $ctl->run('db:user:delete', ['user' => $extra->username]);
        }

        AuditLog::record('db.delete', $db->name);
        return true;
    }
}
