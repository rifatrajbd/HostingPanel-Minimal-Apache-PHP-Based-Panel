<?php

namespace App\Filament\Resources\FtpAccountResource\Pages;

use App\Filament\Resources\FtpAccountResource;
use App\Models\AuditLog;
use App\Models\FtpAccount;
use App\Models\Site;
use App\Services\PanelCtl;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CreateFtpAccount extends CreateRecord
{
    protected static string $resource = FtpAccountResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $username = strtolower($data['username']);
        $site = Site::findOrFail($data['site_id']);
        $generated = empty($data['password']);
        $password = $generated ? Str::password(16, symbols: false) : $data['password'];

        $result = app(PanelCtl::class)->run(
            'ftp:create',
            ['username' => $username, 'domain' => $site->domain],
            $password . "\n",
        );
        if (!$result->ok()) {
            Notification::make()->title('ftp:create failed')->body($result->output())
                ->danger()->persistent()->send();
            $this->halt();
        }

        $account = FtpAccount::create(['username' => $username, 'site_id' => $site->id]);
        AuditLog::record('ftp.create', "{$username} -> {$site->domain}");

        Notification::make()
            ->title("SFTP account {$username} created")
            ->body("Host: {$site->domain} (SFTP, port 22) — User: {$username}"
                . ($generated ? " — Password: {$password} — save it now." : ''))
            ->success()->persistent()->send();

        return $account;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
