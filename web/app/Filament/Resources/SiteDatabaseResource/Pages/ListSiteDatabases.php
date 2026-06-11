<?php

namespace App\Filament\Resources\SiteDatabaseResource\Pages;

use App\Filament\Resources\SiteDatabaseResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSiteDatabases extends ListRecords
{
    protected static string $resource = SiteDatabaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
