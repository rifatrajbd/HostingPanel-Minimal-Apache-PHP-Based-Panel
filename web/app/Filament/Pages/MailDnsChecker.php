<?php

namespace App\Filament\Pages;

use App\Models\MailDomain;
use App\Services\PanelCtl;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class MailDnsChecker extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationGroup = 'Mail';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationLabel = 'DNS Checker';
    protected static ?string $title = 'Mail DNS Checker';

    protected static string $view = 'filament.pages.mail-dns-checker';

    public ?string $domain = null;

    /** @var array<int, array<string, string>> */
    public array $rows = [];

    public bool $checked = false;

    public function mount(): void
    {
        $this->domain = MailDomain::query()->orderBy('domain')->value('domain');
        $this->form->fill(['domain' => $this->domain]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('domain')
                ->label('Mail domain')
                ->options(MailDomain::query()->orderBy('domain')->pluck('domain', 'domain'))
                ->required()
                ->live()
                ->afterStateUpdated(fn () => $this->reset(['rows', 'checked']))
                ->native(false),
        ]);
    }

    public function check(): void
    {
        $domain = $this->form->getState()['domain'] ?? null;
        if (!$domain) {
            Notification::make()->title('Pick a mail domain first.')->warning()->send();
            return;
        }

        $result = app(PanelCtl::class)->run('mail:dnscheck:json', ['domain' => $domain]);
        $decoded = json_decode($result->stdout, true);

        if (!$result->ok()) {
            Notification::make()->title('DNS check failed')->body($result->output())->danger()->persistent()->send();
            return;
        }

        $this->rows = is_array($decoded) ? $decoded : [];
        $this->domain = $domain;
        $this->checked = true;
    }
}
