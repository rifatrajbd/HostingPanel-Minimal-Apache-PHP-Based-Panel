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
                    Infolists\Components\TextEntry::make('domain')
                        ->hiddenLabel()
                        ->columnSpanFull()
                        ->html()
                        ->formatStateUsing(fn (string $state) => static::renderDns($state)),
                ]),
        ]);
    }

    /** Render the live, structured DNS records (from panelctl mail:dns) as a table. */
    protected static function renderDns(string $domain): string
    {
        $result = app(\App\Services\PanelCtl::class)->run('mail:dns', ['domain' => $domain]);
        $records = json_decode($result->stdout, true);

        if (!is_array($records) || $records === []) {
            return '<span class="text-gray-500">DNS records are only available on the live server.</span>';
        }

        $rows = '';
        foreach ($records as $r) {
            $rows .= '<tr class="border-t border-gray-100 dark:border-white/5 align-top">'
                . '<td class="py-2 pr-4 font-mono text-xs whitespace-nowrap">' . e($r['host'] ?? '') . '</td>'
                . '<td class="py-2 pr-4"><span class="inline-flex rounded bg-primary-500/10 text-primary-600 px-1.5 text-xs font-medium">' . e($r['type'] ?? '') . '</span></td>'
                . '<td class="py-2 font-mono text-xs break-all">' . e($r['value'] ?? '') . '</td></tr>';
        }

        return '<div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="text-left text-xs text-gray-500">'
            . '<th class="pb-2 pr-4">Host</th><th class="pb-2 pr-4">Type</th><th class="pb-2">Value</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table></div>'
            . '<p class="text-xs text-gray-500 mt-3">Also set the reverse DNS (PTR) of your server IP to '
            . 'mail.' . e($domain) . ' with your VPS provider — important for deliverability.</p>';
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
