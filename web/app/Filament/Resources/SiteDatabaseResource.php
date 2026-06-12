<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SiteDatabaseResource\Pages;
use App\Models\SiteDatabase;
use App\Services\PanelCtl;
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

    /** Memoized size/table stats from panelctl db:list (one call per request). */
    public static function stats(): array
    {
        static $cache = null;
        if ($cache === null) {
            $result = app(PanelCtl::class)->run('db:list');
            $decoded = json_decode($result->stdout, true);
            $cache = is_array($decoded) ? $decoded : [];
        }
        return $cache;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->required()
                ->placeholder('mybb')
                ->regex('/^[a-z][a-z0-9_]{2,31}$/')
                ->helperText('3-32 chars: lowercase letters, digits, underscores; start with a letter.')
                ->disabledOn('edit')
                ->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('db_user')
                ->label('Database user')
                ->required()
                ->placeholder('mybb_user')
                ->regex('/^[a-z][a-z0-9_]{2,31}$/')
                ->disabledOn('edit'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable()->weight('medium'),
                Tables\Columns\TextColumn::make('db_user')->label('Primary user'),
                Tables\Columns\TextColumn::make('size')
                    ->label('Size')
                    ->state(fn (SiteDatabase $r) => isset(static::stats()[$r->name])
                        ? static::stats()[$r->name]['size_mb'] . ' MB' : '—'),
                Tables\Columns\TextColumn::make('tables')
                    ->label('Tables')
                    ->state(fn (SiteDatabase $r) => static::stats()[$r->name]['tables'] ?? 0),
                Tables\Columns\TextColumn::make('users_count')->counts('users')->label('Extra users'),
                Tables\Columns\TextColumn::make('created_at')->date()->label('Created'),
            ])
            ->actions([
                Tables\Actions\Action::make('manage')
                    ->label('Manage')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->url(fn (SiteDatabase $r) => Pages\ManageDatabase::getUrl(['record' => $r])),
                Tables\Actions\DeleteAction::make(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('phpmyadmin')
                    ->label('phpMyAdmin')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url('/phpmyadmin/', shouldOpenInNewTab: true),
            ])
            ->emptyStateHeading('No databases yet');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSiteDatabases::route('/'),
            'create' => Pages\CreateSiteDatabase::route('/create'),
            'manage' => Pages\ManageDatabase::route('/{record}/manage'),
        ];
    }
}
