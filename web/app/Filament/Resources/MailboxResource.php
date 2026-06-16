<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MailboxResource\Pages;
use App\Models\MailDomain;
use App\Models\Mailbox;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
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

    /** Domain part of the mailbox address (the mail host is mail.<domain>). */
    protected static function domainOf(Mailbox $record): string
    {
        return $record->mailDomain?->domain ?? substr(strrchr($record->address, '@') ?: '@', 1);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Mail client settings')
                ->description('Use these to set up this mailbox in Outlook, Thunderbird, Apple Mail or a phone. '
                    . 'Log in with the full email address as the username.')
                ->icon('heroicon-o-envelope')
                ->columns(2)
                ->schema([
                    Infolists\Components\TextEntry::make('address')
                        ->label('Username / email')->copyable()->columnSpanFull(),

                    Infolists\Components\TextEntry::make('imap_host')->label('Incoming server (IMAP)')
                        ->state(fn (Mailbox $record) => 'mail.' . static::domainOf($record))->copyable(),
                    Infolists\Components\TextEntry::make('imap_port')->label('IMAP port / security')
                        ->state('993 — SSL/TLS'),

                    Infolists\Components\TextEntry::make('smtp_host')->label('Outgoing server (SMTP)')
                        ->state(fn (Mailbox $record) => 'mail.' . static::domainOf($record))->copyable(),
                    Infolists\Components\TextEntry::make('smtp_port')->label('SMTP port / security')
                        ->state('587 — STARTTLS'),

                    Infolists\Components\TextEntry::make('password_note')->label('Password')
                        ->state('The password set when the mailbox was created (reset by deleting and recreating).')
                        ->columnSpanFull(),
                    Infolists\Components\TextEntry::make('webmail')->label('Webmail')
                        ->state('/webmail/ — sign in with the full email address')
                        ->url('/webmail/', shouldOpenInNewTab: true)
                        ->color('primary')->columnSpanFull(),
                ]),
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
                Tables\Actions\ViewAction::make()
                    ->label('Mail settings')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->modalHeading(fn (Mailbox $record) => "Settings for {$record->address}"),
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
