<?php

namespace App\Filament\Resources\SiteDatabaseResource\Pages;

use App\Filament\Resources\SiteDatabaseResource;
use App\Models\AuditLog;
use App\Models\SiteDatabase;
use App\Services\PanelCtl;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CreateSiteDatabase extends CreateRecord
{
    protected static string $resource = SiteDatabaseResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $name = strtolower($data['name']);
        $user = strtolower($data['db_user']);
        $password = Str::password(24, symbols: false);

        $result = app(PanelCtl::class)->run(
            'db:create',
            ['name' => $name, 'user' => $user],
            $password . "\n",
        );

        if (!$result->ok()) {
            Notification::make()->title('db:create failed')->body($result->output())
                ->danger()->persistent()->send();
            $this->halt();
        }

        $db = SiteDatabase::create(['name' => $name, 'db_user' => $user]);
        AuditLog::record('db.create', $name);

        Notification::make()
            ->title("Database \"{$name}\" created")
            ->body("User: {$user} — Password: {$password} — save it now, it will not be shown again.")
            ->success()
            ->persistent()
            ->send();

        return $db;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
