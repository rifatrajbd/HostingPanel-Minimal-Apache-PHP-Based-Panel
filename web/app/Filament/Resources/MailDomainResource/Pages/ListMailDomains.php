<?php

namespace App\Filament\Resources\MailDomainResource\Pages;

use App\Filament\Resources\MailDomainResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMailDomains extends ListRecords
{
    protected static string $resource = MailDomainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('webmail')
                ->label('Open Webmail')
                ->icon('heroicon-o-at-symbol')
                ->color('gray')
                ->url('/webmail/', shouldOpenInNewTab: true),
            Actions\CreateAction::make(),
        ];
    }
}
