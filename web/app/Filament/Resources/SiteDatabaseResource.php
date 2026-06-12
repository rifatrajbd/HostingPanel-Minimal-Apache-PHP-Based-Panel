<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SiteDatabaseResource\Pages;
use App\Models\AuditLog;
use App\Models\SiteDatabase;
use App\Services\PanelCtl;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

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
                    ->label('Users')
                    ->icon('heroicon-o-users')
                    ->url(fn (SiteDatabase $r) => Pages\ManageDatabaseUsers::getUrl(['record' => $r])),

                Tables\Actions\Action::make('export')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn (SiteDatabase $r) => route('databases.export', $r))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('import')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('Importing runs the uploaded SQL against this database. Existing tables in the dump are overwritten.')
                    ->form([
                        Forms\Components\FileUpload::make('file')
                            ->label('SQL file (.sql or .sql.gz)')
                            ->disk('local')->directory('db-imports')->preserveFilenames()
                            ->maxSize(262144) // 256 MB
                            ->required(),
                    ])
                    ->action(fn (SiteDatabase $record, array $data) => static::import($record, $data['file'])),

                Tables\Actions\Action::make('password')
                    ->label('Reset password')
                    ->icon('heroicon-o-key')
                    ->color('gray')
                    ->form([
                        Forms\Components\TextInput::make('password')->password()->revealable()
                            ->minLength(12)->helperText('Leave blank to auto-generate.'),
                    ])
                    ->action(fn (SiteDatabase $record, array $data) => static::resetPassword($record, $data['password'] ?? '')),

                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('No databases yet');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSiteDatabases::route('/'),
            'create' => Pages\CreateSiteDatabase::route('/create'),
            'users' => Pages\ManageDatabaseUsers::route('/{record}/users'),
        ];
    }

    private static function resetPassword(SiteDatabase $record, string $password): void
    {
        $generated = $password === '';
        $password = $generated ? Str::password(20, symbols: false) : $password;

        $result = app(PanelCtl::class)->run('db:password', ['user' => $record->db_user], $password . "\n");
        if ($result->ok()) {
            AuditLog::record('db.password', $record->db_user);
            Notification::make()->title("Password reset for {$record->db_user}")
                ->body($generated ? "New password: {$password}" : null)
                ->success()->persistent()->send();
        } else {
            Notification::make()->title('Failed')->body($result->output())->danger()->persistent()->send();
        }
    }

    private static function import(SiteDatabase $record, string $stored): void
    {
        $full = storage_path('app/' . $stored);
        $stage = config('hostingpanel.uploads');
        if (!is_dir($stage)) {
            @mkdir($stage, 0750, true);
        }
        $tmp = rtrim($stage, '/') . '/' . bin2hex(random_bytes(8)) . '-' . basename($full);

        if (!@copy($full, $tmp)) {
            Notification::make()->title('Could not stage the upload')->danger()->send();
            return;
        }
        @unlink($full);

        $result = app(PanelCtl::class)->run('db:import', ['name' => $record->name, 'src' => $tmp]);
        @unlink($tmp);

        if ($result->ok()) {
            AuditLog::record('db.import', $record->name);
            Notification::make()->title($result->output())->success()->send();
        } else {
            Notification::make()->title('Import failed')->body($result->output())->danger()->persistent()->send();
        }
    }
}
