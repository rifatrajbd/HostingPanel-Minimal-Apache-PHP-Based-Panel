<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\Site;
use App\Services\PanelCtl;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SslManager extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-lock-closed';
    protected static ?string $navigationGroup = 'Hosting';
    protected static ?int $navigationSort = 3;
    protected static ?string $title = 'SSL Manager';

    protected static string $view = 'filament.pages.ssl-manager';

    public array $certs = [];

    public function mount(): void
    {
        $this->loadCerts();
    }

    public function loadCerts(): void
    {
        $result = app(PanelCtl::class)->run('ssl:list');
        $decoded = json_decode($result->stdout, true);
        $this->certs = is_array($decoded) ? $decoded : [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('issue')
                ->label('Issue certificate')
                ->icon('heroicon-o-plus')
                ->form([
                    Forms\Components\Select::make('domain')
                        ->options(Site::where('ssl_enabled', false)->pluck('domain', 'domain'))
                        ->required()
                        ->searchable(),
                    Forms\Components\Checkbox::make('www')->label('Also include www subdomain'),
                ])
                ->action(function (array $data) {
                    $flags = ['domain' => $data['domain']];
                    if ($data['www'] ?? false) {
                        $flags['www'] = '1';
                    }
                    $result = app(PanelCtl::class)->run('ssl:issue', $flags);
                    if ($result->ok()) {
                        Site::where('domain', $data['domain'])->update(['ssl_enabled' => true]);
                        AuditLog::record('ssl.issue', $data['domain']);
                        Notification::make()->title('Certificate issued')->success()->send();
                    } else {
                        Notification::make()->title('Failed')->body($result->output())->danger()->persistent()->send();
                    }
                    $this->loadCerts();
                }),
        ];
    }

    public function renew(string $domain): void
    {
        $result = app(PanelCtl::class)->run('ssl:renew', ['domain' => $domain]);
        Notification::make()->title($result->ok() ? 'Renew triggered' : 'Failed')
            ->body($result->output())->{$result->ok() ? 'success' : 'danger'}()->send();
        AuditLog::record('ssl.renew', $domain);
        $this->loadCerts();
    }

    public function deleteCert(string $domain): void
    {
        $result = app(PanelCtl::class)->run('ssl:delete', ['domain' => $domain]);
        if ($result->ok()) {
            Site::where('domain', $domain)->update(['ssl_enabled' => false]);
            AuditLog::record('ssl.delete', $domain);
        }
        Notification::make()->title($result->ok() ? 'Certificate deleted' : 'Failed')
            ->body($result->output())->{$result->ok() ? 'success' : 'danger'}()->send();
        $this->loadCerts();
    }
}
