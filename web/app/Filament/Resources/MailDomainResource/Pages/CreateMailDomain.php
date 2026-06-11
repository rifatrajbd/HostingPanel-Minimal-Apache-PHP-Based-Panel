<?php

namespace App\Filament\Resources\MailDomainResource\Pages;

use App\Filament\Resources\MailDomainResource;
use App\Models\AuditLog;
use App\Models\MailDomain;
use App\Services\PanelCtl;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateMailDomain extends CreateRecord
{
    protected static string $resource = MailDomainResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $domain = strtolower($data['domain']);

        $result = app(PanelCtl::class)->run('mail:domain:add', ['domain' => $domain]);
        if (!$result->ok()) {
            Notification::make()->title('mail:domain:add failed')->body($result->output())
                ->danger()->persistent()->send();
            $this->halt();
        }

        $record = MailDomain::create([
            'domain' => $domain,
            'dkim_selector' => 'mail',
            'dkim_dns' => trim($result->stdout),
        ]);
        AuditLog::record('mail.domain.add', $domain);

        Notification::make()
            ->title("Mail domain {$domain} added")
            ->body('Open it to see the DNS records you must create.')
            ->success()->send();

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
