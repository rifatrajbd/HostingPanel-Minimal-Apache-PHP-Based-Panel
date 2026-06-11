<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SiteResource\Pages;
use App\Models\Site;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SiteResource extends Resource
{
    protected static ?string $model = Site::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';
    protected static ?string $navigationGroup = 'Hosting';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'domain';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('domain')
                ->required()
                ->placeholder('forum.example.com')
                ->helperText('Point this domain\'s A record at this server before issuing SSL.')
                ->regex('/^(?=.{4,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/')
                ->disabledOn('edit')
                ->unique(ignoreRecord: true),
            Forms\Components\Select::make('php_version')
                ->required()
                ->options(fn () => collect(config('hostingpanel.php_versions'))
                    ->mapWithKeys(fn ($v) => [$v => "PHP {$v}" . ($v === '7.4' ? ' (end-of-life)' : '')])
                    ->all())
                ->default('8.1'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('domain')
                    ->searchable()
                    ->sortable()
                    ->url(fn (Site $r) => "https://{$r->domain}", true)
                    ->color('primary'),
                Tables\Columns\TextColumn::make('php_version')
                    ->label('PHP')
                    ->badge(),
                Tables\Columns\IconColumn::make('ssl_enabled')
                    ->label('SSL')
                    ->boolean(),
                Tables\Columns\IconColumn::make('cf_only')
                    ->label('CF-only')
                    ->boolean(),
                Tables\Columns\TextColumn::make('doc_root')
                    ->label('Document root')
                    ->color('gray')
                    ->size('xs'),
            ])
            ->actions([
                Tables\Actions\Action::make('manage')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->url(fn (Site $r) => Pages\ManageSite::getUrl(['record' => $r])),
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('No sites yet')
            ->emptyStateDescription('Create your first site to get started.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSites::route('/'),
            'create' => Pages\CreateSite::route('/create'),
            'manage' => Pages\ManageSite::route('/{record}/manage'),
        ];
    }
}
