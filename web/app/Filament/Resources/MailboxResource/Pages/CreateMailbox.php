<?php

namespace App\Filament\Resources\MailboxResource\Pages;

use App\Filament\Resources\MailboxResource;
use App\Models\AuditLog;
use App\Models\MailDomain;
use App\Models\Mailbox;
use App\Services\PanelCtl;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CreateMailbox extends CreateRecord
{
    protected static string $resource = MailboxResource::class;

    protected static ?string $title = 'New mailbox';

    protected function handleRecordCreation(array $data): Model
    {
        $domain = MailDomain::findOrFail($data['mail_domain_id']);
        $address = strtolower($data['local_part']) . '@' . $domain->domain;

        $generated = empty($data['password']);
        $password = $generated ? Str::password(16, symbols: false) : $data['password'];

        $result = app(PanelCtl::class)->run(
            'mail:mailbox:add',
            ['address' => $address],
            $password . "\n",
        );

        if (!$result->ok()) {
            Notification::make()->title('Mailbox add failed')->body($result->output())
                ->danger()->persistent()->send();
            $this->halt();
        }

        AuditLog::record('mail.mailbox.add', $address);

        if ($generated) {
            Notification::make()->title("Mailbox {$address} created")
                ->body("Password: {$password} — save it now, it won't be shown again.")
                ->success()->persistent()->send();
        }

        return Mailbox::create([
            'mail_domain_id' => $domain->id,
            'address' => $address,
        ]);
    }
}
