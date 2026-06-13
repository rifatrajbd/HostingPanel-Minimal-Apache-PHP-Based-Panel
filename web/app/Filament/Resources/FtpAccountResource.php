<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FtpAccountResource\Pages;
use App\Models\AuditLog;
use App\Models\FtpAccount;
use App\Models\Site;
use App\Services\PanelCtl;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class FtpAccountResource extends Resource
{
    protected static ?string $model = FtpAccount::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-on-square-stack';
    protected static ?string $navigationGroup = 'Hosting';
    protected static ?int $navigationSort = 6;
    protected static ?string $navigationLabel = 'SFTP Accounts';
    protected static ?string $modelLabel = 'SFTP account';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('username')
                ->required()
                ->regex('/^[a-z][a-z0-9_-]{2,31}$/')
                ->helperText('3-32 chars: a-z, 0-9, _ or -, starting with a letter.')
                ->disabledOn('edit')
                ->unique(ignoreRecord: true),
            Forms\Components\Select::make('site_id')
                ->label('Site')
                ->options(Site::orderBy('domain')->pluck('domain', 'id'))
                ->required()
                ->disabledOn('edit')
                ->helperText('The account is chrooted to this site and lands in its htdocs/.'),
            Forms\Components\TextInput::make('password')
                ->password()
                ->revealable()
                ->minLength(8)
                ->dehydrated(false)
                ->helperText('Leave blank to auto-generate.')
                ->visibleOn('create'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('username')->searchable(),
                Tables\Columns\TextColumn::make('site.domain')->label('Site'),
                Tables\Columns\TextColumn::make('created_at')->date()->label('Created'),
            ])
            ->actions([
                Tables\Actions\Action::make('info')
                    ->label('Connection info')
                    ->icon('heroicon-o-information-circle')
                    ->color('gray')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->infolist(fn (FtpAccount $record) => [
                        \Filament\Infolists\Components\TextEntry::make('protocol')->state('SFTP (SSH File Transfer Protocol)'),
                        \Filament\Infolists\Components\TextEntry::make('host')
                            ->state(app(\App\Services\SystemStats::class)->addresses()['ipv4'] ?? $record->site->domain)
                            ->helperText('You can also use the server hostname or your panel domain.'),
                        \Filament\Infolists\Components\TextEntry::make('port')->state('22'),
                        \Filament\Infolists\Components\TextEntry::make('username')->state($record->username)->copyable(),
                        \Filament\Infolists\Components\TextEntry::make('folder')
                            ->label('Accessible folder')
                            ->state("/var/www/{$record->site->domain}  →  upload into htdocs/")
                            ->helperText('The account is chrooted to its site and lands in htdocs/ (the web root).'),
                    ]),
                Tables\Actions\Action::make('password')
                    ->icon('heroicon-o-key')
                    ->form([
                        Forms\Components\TextInput::make('password')->password()->revealable()
                            ->minLength(8)->helperText('Leave blank to auto-generate.'),
                    ])
                    ->action(function (FtpAccount $record, array $data) {
                        $generated = empty($data['password']);
                        $password = $generated ? Str::password(16, symbols: false) : $data['password'];
                        $result = app(PanelCtl::class)->run('ftp:password',
                            ['username' => $record->username], $password . "\n");
                        if ($result->ok()) {
                            AuditLog::record('ftp.password', $record->username);
                            Notification::make()->title("Password updated for {$record->username}")
                                ->body($generated ? "New password: {$password}" : null)
                                ->success()->persistent()->send();
                        } else {
                            Notification::make()->title('Failed')->body($result->output())->danger()->send();
                        }
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('No SFTP accounts yet');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFtpAccounts::route('/'),
            'create' => Pages\CreateFtpAccount::route('/create'),
        ];
    }
}
