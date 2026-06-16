<?php

namespace App\Filament\Resources\MailboxResource\Pages;

use App\Filament\Resources\MailboxResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMailboxes extends ListRecords
{
    protected static string $resource = MailboxResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('New mailbox'),
        ];
    }
}
