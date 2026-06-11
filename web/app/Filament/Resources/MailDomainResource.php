<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MailDomainResource\Pages;
use App\Filament\Resources\MailDomainResource\RelationManagers\MailboxesRelationManager;
use App\Models\MailDomain;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MailDomainResource extends Resource
{
    protected static ?string $model = MailDomain::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';
    protected static ?string $navigationGroup = 'Mail';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Mail Domains';
    protected static ?string $modelLabel = 'mail domain';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('domain')
                ->required()
                ->placeholder('example.com')
                ->regex('/^(?=.{4,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/')
                ->disabledOn('edit')
                ->unique(ignoreRecord: true),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\TextEntry::make('domain'),
            Infolists\Components\TextEntry::make('dkim_dns')
                ->label('DNS records to create')
                ->placeholder('No DNS info recorded.')
                ->columnSpanFull()
                ->copyable()
                ->extraAttributes(['style' => 'white-space: pre-wrap; font-family: monospace; font-size: 12px;']),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('domain')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('mailboxes_count')->counts('mailboxes')->label('Mailboxes'),
                Tables\Columns\TextColumn::make('created_at')->date()->label('Created'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('DNS records'),
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('No mail domains yet');
    }

    public static function getRelations(): array
    {
        return [
            MailboxesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMailDomains::route('/'),
            'create' => Pages\CreateMailDomain::route('/create'),
            'view' => Pages\ViewMailDomain::route('/{record}'),
        ];
    }
}
