<?php

namespace App\Filament\Resources\FtpAccountResource\Pages;

use App\Filament\Resources\FtpAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFtpAccounts extends ListRecords
{
    protected static string $resource = FtpAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
