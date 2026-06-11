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
            Actions\CreateAction::make(),
        ];
    }
}
