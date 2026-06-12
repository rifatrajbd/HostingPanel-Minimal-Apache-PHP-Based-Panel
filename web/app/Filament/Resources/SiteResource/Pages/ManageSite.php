<?php

namespace App\Filament\Resources\SiteResource\Pages;

use App\Filament\Resources\SiteResource;
use App\Models\AuditLog;
use App\Models\Site;
use App\Services\PanelCtl;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;

class ManageSite extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = SiteResource::class;
    protected static string $view = 'filament.resources.site-resource.pages.manage-site';

    public Site $record;
    public ?array $phpData = [];
    public ?array $cronData = [];

    public function mount(Site $record): void
    {
        $this->record = $record;
        $ini = $record->ini ?? [];
        $this->phpForm->fill([
            'php_version' => $record->php_version,
            'memory_limit' => $ini['memory_limit'] ?? '256M',
            'upload_max_filesize' => $ini['upload_max_filesize'] ?? '64M',
            'post_max_size' => $ini['post_max_size'] ?? '64M',
            'max_execution_time' => $ini['max_execution_time'] ?? '120',
            'display_errors' => ($ini['display_errors'] ?? 'off') === 'on',
        ]);
        $this->cronForm->fill([
            'jobs' => $record->cronJobs->map(fn ($j) => [
                'schedule' => $j->schedule, 'command' => $j->command,
            ])->all(),
        ]);
    }

    public function getTitle(): string
    {
        return $this->record->domain;
    }

    /** @return array{ipv4: ?string, ipv6: ?string} */
    public function serverAddresses(): array
    {
        return app(\App\Services\SystemStats::class)->addresses();
    }

    protected function getForms(): array
    {
        return ['phpForm', 'cronForm'];
    }

    public function phpForm(Form $form): Form
    {
        return $form
            ->statePath('phpData')
            ->schema([
                Forms\Components\Section::make('PHP settings')
                    ->description('Per-site version and ini limits.')
                    ->schema([
                        Forms\Components\Select::make('php_version')
                            ->options(fn () => collect(config('hostingpanel.php_versions'))
                                ->mapWithKeys(fn ($v) => [$v => "PHP {$v}"])->all())
                            ->required(),
                        Forms\Components\Grid::make(4)->schema([
                            Forms\Components\TextInput::make('memory_limit')->regex('/^\d{1,5}M$/'),
                            Forms\Components\TextInput::make('upload_max_filesize')->regex('/^\d{1,5}M$/'),
                            Forms\Components\TextInput::make('post_max_size')->regex('/^\d{1,5}M$/'),
                            Forms\Components\TextInput::make('max_execution_time')->numeric()->minValue(1)->maxValue(3600),
                        ]),
                        Forms\Components\Toggle::make('display_errors')
                            ->helperText('Debugging only — never leave on in production.'),
                    ]),
            ]);
    }

    public function cronForm(Form $form): Form
    {
        return $form
            ->statePath('cronData')
            ->schema([
                Forms\Components\Section::make('Cron jobs')
                    ->description('Run as this site\'s system user. Saving syncs all jobs.')
                    ->schema([
                        Forms\Components\Repeater::make('jobs')
                            ->schema([
                                Forms\Components\TextInput::make('schedule')
                                    ->placeholder('*/15 * * * *')
                                    ->regex('#^[0-9*,/\-]+(\s+[0-9*,/\-]+){4}$#')
                                    ->required()
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('command')
                                    ->placeholder('php ' . $this->record->doc_root . '/task.php')
                                    ->maxLength(500)
                                    ->required()
                                    ->columnSpan(2),
                            ])
                            ->columns(3)
                            ->addActionLabel('Add cron job')
                            ->reorderable(false),
                    ]),
            ]);
    }

    public function savePhp(): void
    {
        $data = $this->phpForm->getState();
        $ini = [
            'memory_limit' => strtoupper($data['memory_limit']),
            'upload_max_filesize' => strtoupper($data['upload_max_filesize']),
            'post_max_size' => strtoupper($data['post_max_size']),
            'max_execution_time' => (string) $data['max_execution_time'],
            'display_errors' => $data['display_errors'] ? 'on' : 'off',
        ];
        $newVersion = $data['php_version'];
        $old = $this->record->php_version;
        $ctl = app(PanelCtl::class);

        $result = $old !== $newVersion
            ? $ctl->run('site:php', ['domain' => $this->record->domain, 'old' => $old, 'new' => $newVersion], json_encode($ini))
            : $ctl->run('site:ini', ['domain' => $this->record->domain, 'php' => $newVersion], json_encode($ini));

        if ($result->ok()) {
            $this->record->update(['php_version' => $newVersion, 'ini' => $ini]);
            AuditLog::record('site.php', "{$this->record->domain} -> {$newVersion}");
            Notification::make()->title('PHP settings applied')->success()->send();
        } else {
            Notification::make()->title('Failed')->body($result->output())->danger()->persistent()->send();
        }
    }

    public function saveCron(): void
    {
        $jobs = collect($this->cronForm->getState()['jobs'] ?? [])
            ->map(fn ($j) => ['schedule' => trim($j['schedule']), 'command' => trim($j['command'])])
            ->values();

        $result = app(PanelCtl::class)->run(
            'cron:sync',
            ['domain' => $this->record->domain],
            $jobs->toJson(),
        );

        if ($result->ok()) {
            $this->record->cronJobs()->delete();
            foreach ($jobs as $j) {
                $this->record->cronJobs()->create($j);
            }
            AuditLog::record('site.cron', $this->record->domain);
            Notification::make()->title('Cron jobs synced')->success()->send();
        } else {
            Notification::make()->title('Failed')->body($result->output())->danger()->persistent()->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('issueSsl')
                ->label($this->record->ssl_enabled ? 'SSL active' : 'Issue SSL')
                ->icon('heroicon-o-lock-closed')
                ->color($this->record->ssl_enabled ? 'success' : 'primary')
                ->disabled($this->record->ssl_enabled)
                ->form([
                    Forms\Components\Checkbox::make('www')->label('Also include www.' . $this->record->domain),
                ])
                ->action(function (array $data) {
                    $flags = ['domain' => $this->record->domain];
                    if ($data['www'] ?? false) {
                        $flags['www'] = '1';
                    }
                    $result = app(PanelCtl::class)->run('ssl:issue', $flags);
                    if ($result->ok()) {
                        $this->record->update(['ssl_enabled' => true]);
                        AuditLog::record('ssl.issue', $this->record->domain);
                        Notification::make()->title('Certificate issued')->success()->send();
                    } else {
                        Notification::make()->title('ssl:issue failed')->body($result->output())->danger()->persistent()->send();
                    }
                }),

            Actions\Action::make('toggleCf')
                ->label($this->record->cf_only ? 'Disable Cloudflare-only' : 'Enable Cloudflare-only')
                ->icon('heroicon-o-shield-check')
                ->color($this->record->cf_only ? 'warning' : 'gray')
                ->requiresConfirmation()
                ->modalDescription('Cloudflare-only blocks direct-to-origin traffic. The domain must be proxied (orange cloud) in Cloudflare or the site goes down.')
                ->action(function () {
                    $enable = !$this->record->cf_only;
                    $result = app(PanelCtl::class)->run('site:cfonly', [
                        'domain' => $this->record->domain,
                        'enable' => $enable ? '1' : '0',
                    ]);
                    if ($result->ok()) {
                        $this->record->update(['cf_only' => $enable]);
                        AuditLog::record('site.cfonly', $this->record->domain . ($enable ? ' on' : ' off'));
                        Notification::make()->title($result->output())->success()->send();
                    } else {
                        Notification::make()->title('Failed')->body($result->output())->danger()->persistent()->send();
                    }
                }),

            Actions\Action::make('ipMode')
                ->label('IP mode: ' . strtoupper($this->record->ip_mode))
                ->icon('heroicon-o-signal')
                ->color('gray')
                ->form([
                    Forms\Components\Radio::make('mode')
                        ->label('Serve this site over')
                        ->options([
                            'both' => 'IPv4 and IPv6 (default)',
                            'ipv4' => 'IPv4 only — refuse IPv6 clients',
                            'ipv6' => 'IPv6 only — refuse IPv4 clients',
                        ])
                        ->default($this->record->ip_mode)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $result = app(PanelCtl::class)->run('site:ipmode', [
                        'domain' => $this->record->domain,
                        'mode' => $data['mode'],
                    ]);
                    if ($result->ok()) {
                        $this->record->update(['ip_mode' => $data['mode']]);
                        AuditLog::record('site.ipmode', "{$this->record->domain} {$data['mode']}");
                        Notification::make()->title($result->output())->success()->send();
                    } else {
                        Notification::make()->title('Failed')->body($result->output())->danger()->persistent()->send();
                    }
                }),

            Actions\Action::make('files')
                ->label('File Manager')
                ->icon('heroicon-o-folder')
                ->color('gray')
                ->url(fn () => \App\Filament\Pages\FileManager::getUrl(['site' => $this->record->id])),
        ];
    }
}
