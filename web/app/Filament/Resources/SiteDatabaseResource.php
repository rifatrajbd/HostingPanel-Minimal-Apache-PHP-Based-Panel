<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SiteDatabaseResource\Pages;
use App\Models\SiteDatabase;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SiteDatabaseResource extends Resource
{
    protected static ?string $model = SiteDatabase::class;

    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';
    protected static ?string $navigationGroup = 'Hosting';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'Databases';
    protected static ?string $modelLabel = 'database';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->required()
                ->placeholder('mybb')
                ->regex('/^[a-z][a-z0-9_]{2,31}$/')
                ->helperText('3-32 chars: lowercase letters, digits, underscores; start with a letter.')
                ->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('db_user')
                ->label('Database user')
                ->required()
                ->placeholder('mybb_user')
                ->regex('/^[a-z][a-z0-9_]{2,31}$/'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('db_user')->label('User'),
                Tables\Columns\TextColumn::make('created_at')->date()->label('Created'),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('No databases yet');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSiteDatabases::route('/'),
            'create' => Pages\CreateSiteDatabase::route('/create'),
        ];
    }
}
