<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\DatabaseUser;
use App\Services\PanelCtl;
use Filament\Notifications\Notification;

class DatabaseUserObserver
{
    public function deleting(DatabaseUser $user): bool
    {
        $result = app(PanelCtl::class)->run('db:user:delete', ['user' => $user->username]);
        if (!$result->ok()) {
            Notification::make()->title('Failed to drop user')->body($result->output())
                ->danger()->persistent()->send();
            return false;
        }
        AuditLog::record('db.user.delete', $user->username);
        return true;
    }
}
