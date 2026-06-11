<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Services\PanelCtl;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class PhpManager extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-command-line';
    protected static ?string $navigationGroup = 'System';
    protected static ?int $navigationSort = 2;
    protected static ?string $title = 'PHP Manager';

    protected static string $view = 'filament.pages.php-manager';

    public ?array $data = [];
    public array $versions = [];

    public function mount(): void
    {
        $this->form->fill(['action' => 'install']);
        $this->loadVersions();
    }

    public function loadVersions(): void
    {
        $ctl = app(PanelCtl::class);
        $this->versions = [];
        foreach (config('hostingpanel.php_versions') as $version) {
            $result = $ctl->run('php:ext-list', ['php' => $version]);
            $exts = json_decode($result->stdout, true);
            $this->versions[$version] = is_array($exts) ? $exts : [];
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Forms\Components\Section::make('Manage extension')
                    ->schema([
                        Forms\Components\Grid::make(3)->schema([
                            Forms\Components\Select::make('php')
                                ->label('PHP version')
                                ->options(collect(config('hostingpanel.php_versions'))
                                    ->mapWithKeys(fn ($v) => [$v => "PHP {$v}"])->all())
                                ->required(),
                            Forms\Components\TextInput::make('name')
                                ->label('Extension')
                                ->placeholder('imagick')
                                ->datalist(['imagick', 'redis', 'memcached', 'bcmath', 'soap', 'apcu', 'opcache'])
                                ->regex('/^[a-z0-9_]{2,30}$/')
                                ->required(),
                            Forms\Components\Select::make('action')
                                ->options(['install' => 'Install', 'enable' => 'Enable', 'disable' => 'Disable'])
                                ->required(),
                        ]),
                    ]),
            ]);
    }

    public function submit(): void
    {
        $data = $this->form->getState();
        $result = app(PanelCtl::class)->run('php:ext', [
            'php' => $data['php'],
            'name' => strtolower($data['name']),
            'action' => $data['action'],
        ]);
        if ($result->ok()) {
            AuditLog::record('php.ext', "{$data['action']} {$data['name']} (PHP {$data['php']})");
        }
        Notification::make()->title($result->ok() ? 'Done' : 'Failed')
            ->body($result->output())->{$result->ok() ? 'success' : 'danger'}()->send();
        $this->loadVersions();
    }
}
