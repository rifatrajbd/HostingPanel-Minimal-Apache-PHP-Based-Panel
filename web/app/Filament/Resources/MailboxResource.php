<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MailboxResource\Pages;
use App\Models\MailDomain;
use App\Models\Mailbox;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MailboxResource extends Resource
{
    protected static ?string $model = Mailbox::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox';
    protected static ?string $navigationGroup = 'Mail';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'Mailboxes';
    protected static ?string $modelLabel = 'mailbox';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('mail_domain_id')
                ->label('Mail domain')
                ->relationship('mailDomain', 'domain')
                ->options(MailDomain::query()->orderBy('domain')->pluck('domain', 'id'))
                ->required()
                ->searchable()
                ->live()
                ->native(false)
                ->helperText(fn () => MailDomain::query()->exists()
                    ? null
                    : 'No mail domains yet — create one under Mail Domains first.'),
            Forms\Components\TextInput::make('local_part')
                ->label('Mailbox name')
                ->required()
                ->regex('/^[a-z0-9._+-]{1,64}$/')
                ->prefixIcon('heroicon-m-at-symbol')
                ->suffix(fn (Forms\Get $get) => ($d = MailDomain::find($get('mail_domain_id')))
                    ? '@' . $d->domain
                    : '@domain')
                ->helperText('Lowercase letters, numbers and . _ + - only.'),
            Forms\Components\TextInput::make('password')
                ->password()
                ->revealable()
                ->minLength(10)
                ->maxLength(128)
                ->helperText('Leave blank to auto-generate a strong password (shown once after saving).'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('address')->label('Email address')->searchable()->sortable()->copyable(),
                Tables\Columns\TextColumn::make('mailDomain.domain')->label('Domain')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->date()->label('Created')->sortable(),
            ])
            ->defaultSort('address')
            ->actions([
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('No mailboxes yet')
            ->emptyStateDescription('Create a mailbox to get started.')
            ->emptyStateIcon('heroicon-o-inbox');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMailboxes::route('/'),
            'create' => Pages\CreateMailbox::route('/create'),
        ];
    }
}
