<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\PanelCtl;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class Settings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationGroup = 'System';
    protected static ?int $navigationSort = 3;
    protected static ?string $title = 'Settings';

    protected static string $view = 'filament.pages.settings';

    public ?array $backupData = [];
    public ?array $domainData = [];
    public string $backupLog = '';

    private const SCHEDULES = [
        'daily' => '0 3 * * *',
        'twice-daily' => '0 3,15 * * *',
        'weekly' => '0 3 * * 0',
        'disabled' => '',
    ];

    public function mount(): void
    {
        $this->backupForm->fill([
            'type' => Setting::get('backup_type', 'ftp'),
            'host' => Setting::get('backup_host'),
            'user' => Setting::get('backup_user'),
            'path' => Setting::get('backup_path', 'hostingpanel-backups'),
            'retention' => (int) Setting::get('backup_retention', '7'),
            'schedule' => Setting::get('backup_schedule', 'disabled'),
        ]);
        $this->domainForm->fill(['domain' => Setting::get('panel_domain')]);

        $result = app(PanelCtl::class)->run('backup:log');
        $this->backupLog = $result->ok() ? trim($result->stdout) : '';
    }

    protected function getForms(): array
    {
        return ['backupForm', 'domainForm'];
    }

    public function domainForm(Form $form): Form
    {
        return $form
            ->statePath('domainData')
            ->schema([
                Forms\Components\Section::make('Panel domain')
                    ->description('Serve the panel on its own domain with a trusted Let\'s Encrypt certificate. Point the domain\'s A record here first.')
                    ->schema([
                        Forms\Components\TextInput::make('domain')
                            ->placeholder('panel.example.com')
                            ->regex('/^(?=.{4,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/'),
                    ]),
            ]);
    }

    public function backupForm(Form $form): Form
    {
        return $form
            ->statePath('backupData')
            ->schema([
                Forms\Components\Section::make('Automatic backups')
                    ->description('Site files + all MySQL databases + panel data, to Google Drive or FTP via rclone.')
                    ->schema([
                        Forms\Components\Grid::make(4)->schema([
                            Forms\Components\Select::make('type')
                                ->label('Destination')
                                ->options(['ftp' => 'FTP server', 'drive' => 'Google Drive'])
                                ->live()
                                ->required(),
                            Forms\Components\Select::make('schedule')
                                ->options([
                                    'daily' => 'Daily 03:00',
                                    'twice-daily' => 'Twice daily',
                                    'weekly' => 'Weekly (Sun)',
                                    'disabled' => 'Disabled',
                                ])
                                ->required(),
                            Forms\Components\TextInput::make('retention')
                                ->label('Keep last N')
                                ->numeric()->minValue(1)->maxValue(365)->default(7),
                            Forms\Components\TextInput::make('path')
                                ->label('Remote folder')
                                ->default('hostingpanel-backups'),
                        ]),
                        Forms\Components\Grid::make(3)
                            ->visible(fn (Forms\Get $get) => $get('type') === 'ftp')
                            ->schema([
                                Forms\Components\TextInput::make('host')->placeholder('ftp.example.com'),
                                Forms\Components\TextInput::make('user')->placeholder('ftp username'),
                                Forms\Components\TextInput::make('pass')->password()->placeholder('ftp password'),
                            ]),
                        Forms\Components\Textarea::make('token')
                            ->label('Google Drive token JSON')
                            ->visible(fn (Forms\Get $get) => $get('type') === 'drive')
                            ->helperText('On your PC run: rclone authorize "drive" — sign in, then paste the JSON here.')
                            ->rows(3),
                    ]),
            ]);
    }

    public function saveDomain(): void
    {
        $domain = strtolower($this->domainForm->getState()['domain'] ?? '');
        if ($domain === '') {
            return;
        }
        $result = app(PanelCtl::class)->run('panel:domain', ['domain' => $domain]);
        if ($result->ok()) {
            Setting::put('panel_domain', $domain);
        }
        AuditLog::record('panel.domain', $domain);
        Notification::make()->title($result->ok() ? 'Panel domain set' : 'Failed')
            ->body($result->output())->{$result->ok() ? 'success' : 'danger'}()->persistent()->send();
    }

    public function saveBackup(): void
    {
        $data = $this->backupForm->getState();
        $config = [
            'type' => $data['type'],
            'path' => $data['path'] ?: 'hostingpanel-backups',
            'retention' => (int) $data['retention'],
        ];
        if ($data['type'] === 'ftp') {
            $config['host'] = $data['host'] ?? '';
            $config['user'] = $data['user'] ?? '';
            $config['pass'] = $data['pass'] ?? '';
        } else {
            $config['token'] = $data['token'] ?? '';
        }

        $ctl = app(PanelCtl::class);
        $result = $ctl->run('backup:config', [], json_encode($config));
        if (!$result->ok()) {
            Notification::make()->title('backup:config failed')->body($result->output())
                ->danger()->persistent()->send();
            return;
        }

        $cron = self::SCHEDULES[$data['schedule']] ?? '';
        $scheduleResult = $cron === ''
            ? $ctl->run('backup:schedule', ['disable' => '1'])
            : $ctl->run('backup:schedule', ['cron' => $cron]);

        Setting::put('backup_type', $data['type']);
        Setting::put('backup_host', $data['type'] === 'ftp' ? ($data['host'] ?? '') : '');
        Setting::put('backup_user', $data['type'] === 'ftp' ? ($data['user'] ?? '') : '');
        Setting::put('backup_path', $config['path']);
        Setting::put('backup_retention', (string) $config['retention']);
        Setting::put('backup_schedule', $data['schedule']);

        AuditLog::record('backup.config', "{$data['type']}, {$data['schedule']}");
        Notification::make()->title('Backup settings saved')->body($scheduleResult->output())
            ->{$scheduleResult->ok() ? 'success' : 'danger'}()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testBackup')
                ->label('Test backup connection')
                ->color('gray')
                ->action(fn () => $this->ctlNotify('backup:test')),
            Action::make('runBackup')
                ->label('Run backup now')
                ->color('success')
                ->action(fn () => $this->ctlNotify('backup:run', 'backup.run')),
            Action::make('selfUpdate')
                ->label('Update panel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Pull the latest code from your repo and redeploy. Sites and data are untouched.')
                ->action(fn () => $this->ctlNotify('panel:self-update', 'panel.self_update')),
        ];
    }

    private function ctlNotify(string $command, ?string $audit = null): void
    {
        $result = app(PanelCtl::class)->run($command);
        if ($audit && $result->ok()) {
            AuditLog::record($audit);
        }
        Notification::make()->title($result->ok() ? 'Done' : 'Failed')
            ->body($result->output())->{$result->ok() ? 'success' : 'danger'}()->persistent()->send();
    }
}
