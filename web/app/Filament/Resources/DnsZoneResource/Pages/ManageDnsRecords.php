<?php

namespace App\Filament\Resources\DnsZoneResource\Pages;

use App\Filament\Resources\DnsZoneResource;
use App\Services\PanelCtl;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables;
use Filament\Tables\Table;

class ManageDnsRecords extends ManageRelatedRecords
{
    protected static string $resource = DnsZoneResource::class;
    protected static string $relationship = 'records';
    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';

    public function getTitle(): string
    {
        return $this->getRecord()->domain . ' — DNS records';
    }

    public static function getNavigationLabel(): string
    {
        return 'Records';
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\Select::make('type')
                    ->options(array_combine(
                        ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SRV', 'CAA'],
                        ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SRV', 'CAA'],
                    ))
                    ->default('A')->live()->required(),
                Forms\Components\TextInput::make('name')
                    ->default('@')
                    ->helperText('Use @ for the domain itself, or a subdomain like "www" or "*".')
                    ->required(),
            ]),
            Forms\Components\TextInput::make('content')
                ->required()
                ->helperText(fn (Forms\Get $get) => match ($get('type')) {
                    'A' => 'IPv4 address, e.g. 203.0.113.10',
                    'AAAA' => 'IPv6 address',
                    'CNAME', 'NS' => 'Target hostname, e.g. example.com',
                    'MX' => 'Mail server hostname (set priority below)',
                    'TXT' => 'Text value (SPF, verification, etc.)',
                    default => '',
                }),
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('ttl')->numeric()->default(3600)->minValue(60)->maxValue(604800),
                Forms\Components\TextInput::make('prio')->label('Priority')->numeric()->default(10)
                    ->visible(fn (Forms\Get $get) => in_array($get('type'), ['MX', 'SRV'], true)),
            ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('type')->badge(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('content')->limit(48)->searchable(),
                Tables\Columns\TextColumn::make('ttl')->label('TTL'),
                Tables\Columns\TextColumn::make('prio')->label('Prio')
                    ->formatStateUsing(fn ($state, $record) => in_array($record->type, ['MX', 'SRV']) ? $state : '—'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Add record'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('Only SOA/NS so far')
            ->emptyStateDescription('The zone already serves SOA + NS. Add your A, MX, TXT and other records here.');
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('checkDns')
                ->label('Check nameservers')
                ->icon('heroicon-o-signal')
                ->color('gray')
                ->action(function () {
                    $zone = $this->getRecord();
                    $result = app(PanelCtl::class)->run('dns:check', ['domain' => $zone->domain]);
                    Notification::make()
                        ->title($result->ok() ? 'DNS check' : 'Check failed')
                        ->body($result->output())
                        ->{$result->ok() ? 'success' : 'danger'}()
                        ->persistent()
                        ->send();
                }),
        ];
    }
}
