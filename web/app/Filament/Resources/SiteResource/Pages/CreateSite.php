<?php

namespace App\Filament\Resources\SiteResource\Pages;

use App\Filament\Resources\SiteResource;
use App\Models\AuditLog;
use App\Models\Site;
use App\Services\PanelCtl;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CreateSite extends CreateRecord
{
    protected static string $resource = SiteResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $domain = strtolower($data['domain']);
        $php = $data['php_version'];

        $result = app(PanelCtl::class)->run('site:create', [
            'domain' => $domain,
            'php' => $php,
        ]);

        if (!$result->ok()) {
            Notification::make()
                ->title('site:create failed')
                ->body($result->output())
                ->danger()
                ->persistent()
                ->send();
            $this->halt();
        }

        $systemUser = 'web-' . substr(preg_replace('/[^a-z0-9]/', '', $domain), 0, 24);
        $site = Site::create([
            'domain' => $domain,
            'php_version' => $php,
            'doc_root' => "/var/www/{$domain}/htdocs",
            'system_user' => $systemUser,
            'ini' => [],
        ]);

        AuditLog::record('site.create', $domain);
        Notification::make()->title("Site {$domain} created")->success()->send();

        return $site;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
