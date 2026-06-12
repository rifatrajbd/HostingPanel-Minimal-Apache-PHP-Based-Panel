<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DnsZoneResource\Pages;
use App\Models\DnsZone;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DnsZoneResource extends Resource
{
    protected static ?string $model = DnsZone::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-europe-africa';
    protected static ?string $navigationGroup = 'Hosting';
    protected static ?int $navigationSort = 5;
    protected static ?string $navigationLabel = 'DNS Zones';
    protected static ?string $modelLabel = 'DNS zone';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('domain')
                ->required()
                ->placeholder('example.com')
                ->regex('/^(?=.{4,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/')
                ->disabledOn('edit')
                ->unique(ignoreRecord: true)
                ->helperText('A zone is created with SOA + NS records. Point your registrar to ns1/ns2.<domain>.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('domain')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('records_count')->counts('records')->label('Records'),
                Tables\Columns\TextColumn::make('created_at')->date()->label('Created'),
            ])
            ->actions([
                Tables\Actions\Action::make('records')
                    ->label('Manage records')
                    ->icon('heroicon-o-list-bullet')
                    ->url(fn (DnsZone $z) => Pages\ManageDnsRecords::getUrl(['record' => $z])),
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('No DNS zones yet');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDnsZones::route('/'),
            'create' => Pages\CreateDnsZone::route('/create'),
            'records' => Pages\ManageDnsRecords::route('/{record}/records'),
        ];
    }
}
