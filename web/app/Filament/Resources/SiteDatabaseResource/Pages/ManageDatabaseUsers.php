<?php

namespace App\Filament\Resources\SiteDatabaseResource\Pages;

use App\Filament\Resources\SiteDatabaseResource;
use App\Models\AuditLog;
use App\Models\DatabaseUser;
use App\Services\PanelCtl;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ManageDatabaseUsers extends ManageRelatedRecords
{
    protected static string $resource = SiteDatabaseResource::class;
    protected static string $relationship = 'users';
    protected static ?string $navigationIcon = 'heroicon-o-users';

    public function getTitle(): string
    {
        return $this->getRecord()->name . ' — additional users';
    }

    public static function getNavigationLabel(): string
    {
        return 'Users';
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('username')
                ->required()
                ->regex('/^[a-z][a-z0-9_]{2,31}$/')
                ->helperText('3-32 chars: a-z, 0-9, _ — start with a letter.')
                ->unique(table: DatabaseUser::class, ignoreRecord: true),
            Forms\Components\Select::make('privileges')
                ->options(['all' => 'Full access (read/write)', 'readonly' => 'Read-only (SELECT)'])
                ->default('all')
                ->required(),
            Forms\Components\TextInput::make('password')
                ->password()->revealable()->minLength(12)
                ->dehydrated(false)
                ->helperText('Leave blank to auto-generate.')
                ->visibleOn('create'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('username')
            ->columns([
                Tables\Columns\TextColumn::make('username')->searchable(),
                Tables\Columns\TextColumn::make('privileges')->badge()
                    ->color(fn ($state) => $state === 'readonly' ? 'gray' : 'success'),
                Tables\Columns\TextColumn::make('created_at')->date()->label('Added'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add user')
                    ->using(function (array $data): Model {
                        $db = $this->getRecord();
                        $generated = empty($data['password']);
                        $password = $generated ? Str::password(20, symbols: false) : $data['password'];

                        $result = app(PanelCtl::class)->run('db:user:add', [
                            'name' => $db->name,
                            'user' => $data['username'],
                            'privileges' => $data['privileges'],
                        ], $password . "\n");

                        if (!$result->ok()) {
                            Notification::make()->title('Failed to add user')->body($result->output())
                                ->danger()->persistent()->send();
                            $this->halt();
                        }

                        AuditLog::record('db.user.add', "{$data['username']} -> {$db->name}");
                        Notification::make()->title("User {$data['username']} added")
                            ->body($generated ? "Password: {$password} — save it now." : null)
                            ->success()->persistent()->send();

                        return $db->users()->create([
                            'username' => $data['username'],
                            'privileges' => $data['privileges'],
                        ]);
                    }),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('No additional users')
            ->emptyStateDescription('The primary user was created with the database. Add more here for separate apps or read-only access.');
    }
}
