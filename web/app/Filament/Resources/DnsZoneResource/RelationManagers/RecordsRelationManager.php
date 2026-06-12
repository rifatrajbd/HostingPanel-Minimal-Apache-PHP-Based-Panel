<?php

namespace App\Filament\Resources\DnsZoneResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class RecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'records';
    protected static ?string $title = 'Records';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\Select::make('type')
                    ->options(array_combine(
                        ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SRV', 'CAA'],
                        ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SRV', 'CAA'],
                    ))
                    ->default('A')
                    ->live()
                    ->required(),
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
                Forms\Components\TextInput::make('prio')
                    ->label('Priority')
                    ->numeric()->default(10)
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
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('Only SOA/NS so far — add your A, MX, TXT records');
    }
}
