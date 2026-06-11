<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\Mailbox;
use App\Services\PanelCtl;
use Filament\Notifications\Notification;

class MailboxObserver
{
    public function deleting(Mailbox $mailbox): bool
    {
        $result = app(PanelCtl::class)->run('mail:mailbox:delete', ['address' => $mailbox->address]);

        if (!$result->ok()) {
            Notification::make()->title('mailbox delete failed')->body($result->output())
                ->danger()->persistent()->send();
            return false;
        }

        AuditLog::record('mail.mailbox.delete', $mailbox->address);
        return true;
    }
}
