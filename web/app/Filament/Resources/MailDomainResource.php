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
            Infolists\Components\Section::make('DNS records to create')
                ->description('Add these at your DNS provider so mail delivers and passes SPF/DKIM/DMARC. Use the "Check DNS" button to verify.')
                ->schema([
                    Infolists\Components\TextEntry::make('dkim_dns')
                        ->hiddenLabel()
                        ->placeholder('No DNS info recorded.')
                        ->columnSpanFull()
                        ->html()
                        ->formatStateUsing(fn (?string $state) => static::renderDns($state)),
                ]),
        ]);
    }

    /** Render the stored DNS text as a clean, copyable table. */
    protected static function renderDns(?string $text): string
    {
        if (!$text) {
            return '<span class="text-gray-500">No DNS info recorded.</span>';
        }
        $rows = '';
        foreach (preg_split('/\r?\n/', $text) as $line) {
            $line = rtrim($line);
            if ($line === '') {
                continue;
            }
            if (str_starts_with($line, '#')) {
                $rows .= '<tr><td colspan="3" class="py-1 text-xs text-gray-500 italic">'
                    . e(ltrim($line, '# ')) . '</td></tr>';
                continue;
            }
            // Split "host TYPE value..." — first token host, second token type.
            $parts = preg_split('/\s+/', $line, 3);
            $host = e($parts[0] ?? '');
            $type = e($parts[1] ?? '');
            $value = e($parts[2] ?? ($parts[1] ?? ''));
            if (($parts[1] ?? '') === '' ) {
                // a continuation / opaque line (e.g. multi-line DKIM) — show raw
                $rows .= '<tr><td colspan="3" class="py-1 font-mono text-xs break-all">' . e($line) . '</td></tr>';
                continue;
            }
            $rows .= '<tr class="border-t border-gray-100 dark:border-white/5">'
                . '<td class="py-1.5 pr-4 font-mono text-xs">' . $host . '</td>'
                . '<td class="py-1.5 pr-4"><span class="inline-flex rounded bg-primary-500/10 text-primary-600 px-1.5 text-xs font-medium">' . $type . '</span></td>'
                . '<td class="py-1.5 font-mono text-xs break-all">' . $value . '</td></tr>';
        }

        return '<table class="w-full text-sm"><thead><tr class="text-left text-xs text-gray-500">'
            . '<th class="pb-1 pr-4">Host</th><th class="pb-1 pr-4">Type</th><th class="pb-1">Value</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table>';
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
