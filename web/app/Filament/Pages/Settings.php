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
    public ?array $accessData = [];
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
        $this->accessForm->fill([
            'ips' => array_values(array_filter(explode(',', Setting::get('panel_access_ips')))),
        ]);

        $result = app(PanelCtl::class)->run('backup:log');
        $this->backupLog = $result->ok() ? trim($result->stdout) : '';
    }

    protected function getForms(): array
    {
        return ['backupForm', 'domainForm', 'accessForm'];
    }

    public function accessForm(Form $form): Form
    {
        return $form
            ->statePath('accessData')
            ->schema([
                Forms\Components\Section::make('Restrict panel access')
                    ->description('Limit who can reach the panel (port 8443) to specific IPs/ranges. '
                        . 'Locked out? Run "panelctl panel:access --open" over SSH.')
                    ->schema([
                        Forms\Components\TagsInput::make('ips')
                            ->label('Allowed IPs / CIDRs')
                            ->placeholder('203.0.113.5 or 198.51.100.0/24')
                            ->helperText('Leave empty and click "Open to all" to remove the restriction.'),
                    ]),
            ]);
    }

    public function saveAccess(): void
    {
        $ips = collect($this->accessForm->getState()['ips'] ?? [])
            ->map(fn ($v) => trim($v))->filter()->unique()->values();

        if ($ips->isEmpty()) {
            Notification::make()->title('Add at least one IP, or use "Open to all".')->warning()->send();
            return;
        }

        $result = app(PanelCtl::class)->run('panel:access', [], $ips->toJson());
        if ($result->ok()) {
            Setting::put('panel_access_ips', $ips->implode(','));
            AuditLog::record('panel.access', $ips->implode(' '));
        }
        Notification::make()->title($result->ok() ? 'Panel access restricted' : 'Failed')
            ->body($result->output())->{$result->ok() ? 'success' : 'danger'}()->persistent()->send();
    }

    public function openAccess(): void
    {
        $result = app(PanelCtl::class)->run('panel:access', ['open' => '1']);
        if ($result->ok()) {
            Setting::put('panel_access_ips', '');
            $this->accessForm->fill(['ips' => []]);
            AuditLog::record('panel.access', 'open');
        }
        Notification::make()->title($result->output())
            ->{$result->ok() ? 'success' : 'danger'}()->send();
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

    public function testBackup(): void
    {
        $this->ctlNotify('backup:test');
    }

    public function runBackup(): void
    {
        $this->ctlNotify('backup:run', 'backup.run');
    }

    public function restoreAction(): Action
    {
        return Action::make('restore')
            ->label('Restore from backup')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Imports all databases and overwrites site files from an uploaded full-backup .tar.gz. Existing data is replaced — this cannot be undone.')
            ->form([
                \Filament\Forms\Components\FileUpload::make('file')
                    ->label('Full backup archive (.tar.gz)')
                    ->disk('local')->directory('restore-uploads')->preserveFilenames()
                    ->maxSize(262144)->required(),
            ])
            ->action(function (array $data) {
                $full = storage_path('app/' . $data['file']);
                $stage = config('hostingpanel.uploads');
                if (!is_dir($stage)) {
                    @mkdir($stage, 0750, true);
                }
                $tmp = rtrim($stage, '/') . '/' . bin2hex(random_bytes(8)) . '-restore.tar.gz';
                if (!@copy($full, $tmp)) {
                    Notification::make()->title('Could not stage the upload')->danger()->send();
                    return;
                }
                @unlink($full);
                $result = app(PanelCtl::class)->run('backup:restore', ['src' => $tmp]);
                @unlink($tmp);
                if ($result->ok()) {
                    AuditLog::record('backup.restore', '');
                }
                Notification::make()->title($result->ok() ? 'Restore complete' : 'Restore failed')
                    ->body($result->output())->{$result->ok() ? 'success' : 'danger'}()->persistent()->send();
            });
    }

    public function getSitesProperty()
    {
        return \App\Models\Site::orderBy('domain')->pluck('domain', 'domain');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('selfUpdate')
                ->label('Update panel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Update panel')
                ->modalDescription('Pull the latest code from your repo and redeploy. Sites and data are untouched. '
                    . 'Confirm with your account password.')
                ->form([
                    \Filament\Forms\Components\TextInput::make('password')
                        ->label('Your account password')
                        ->password()->required()->autocomplete('current-password'),
                ])
                ->action(function (array $data) {
                    if (!\Illuminate\Support\Facades\Hash::check($data['password'], auth()->user()->password)) {
                        Notification::make()->title('Incorrect password — update cancelled.')->danger()->send();
                        return;
                    }
                    $this->ctlNotify('panel:self-update', 'panel.self_update');
                }),
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
