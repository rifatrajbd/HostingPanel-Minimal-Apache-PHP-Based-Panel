<?php

namespace App\Filament\Resources\MailDomainResource\Pages;

use App\Filament\Resources\MailDomainResource;
use App\Services\PanelCtl;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewMailDomain extends ViewRecord
{
    protected static string $resource = MailDomainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('checkDns')
                ->label('Check DNS')
                ->icon('heroicon-o-signal')
                ->color('gray')
                ->action(function () {
                    $result = app(PanelCtl::class)->run('mail:dnscheck', ['domain' => $this->record->domain]);
                    Notification::make()
                        ->title($result->ok() ? 'Mail DNS check' : 'Check failed')
                        ->body($result->output())
                        ->{$result->ok() ? 'success' : 'danger'}()
                        ->persistent()
                        ->send();
                }),
        ];
    }
}
