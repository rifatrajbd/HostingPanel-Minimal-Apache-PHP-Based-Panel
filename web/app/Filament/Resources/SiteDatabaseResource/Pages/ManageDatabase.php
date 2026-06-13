<?php

namespace App\Filament\Resources\SiteDatabaseResource\Pages;

use App\Filament\Resources\SiteDatabaseResource;
use App\Models\AuditLog;
use App\Models\DatabaseUser;
use App\Models\SiteDatabase;
use App\Services\PanelCtl;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Str;

class ManageDatabase extends Page
{
    protected static string $resource = SiteDatabaseResource::class;
    protected static string $view = 'filament.resources.site-database-resource.pages.manage-database';

    public SiteDatabase $record;
    public array $info = [];

    public function mount(SiteDatabase $record): void
    {
        $this->record = $record;
        $this->loadInfo();
    }

    public function loadInfo(): void
    {
        $result = app(PanelCtl::class)->run('db:info', ['name' => $this->record->name]);
        $decoded = json_decode($result->stdout, true);
        $this->info = is_array($decoded) ? $decoded : [];
    }

    public function getTitle(): string
    {
        return $this->record->name;
    }

    public function getBreadcrumb(): string
    {
        return 'Manage';
    }

    /** Primary user + additional users, normalized for the view. */
    public function getUsersProperty()
    {
        $primary = (object) [
            'id' => 0, 'username' => $this->record->db_user, 'privileges' => 'all', 'primary' => true,
        ];
        $extra = $this->record->users()->get()->map(fn (DatabaseUser $u) => (object) [
            'id' => $u->id, 'username' => $u->username, 'privileges' => $u->privileges, 'primary' => false,
        ]);
        return collect([$primary])->concat($extra);
    }

    public function exportUrl(string $format): string
    {
        return route('databases.export', ['database' => $this->record, 'format' => $format]);
    }

    // ---- header actions ----------------------------------------------------

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')->label('Manage Databases')->icon('heroicon-o-circle-stack')
                ->color('gray')->url(SiteDatabaseResource::getUrl('index')),
            Action::make('phpmyadmin')->label('phpMyAdmin')->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')->url('/phpmyadmin-sso', shouldOpenInNewTab: true),
        ];
    }

    // ---- operation actions (rendered in the view) --------------------------

    public function importAction(): Action
    {
        return Action::make('import')->label('Import')->icon('heroicon-o-arrow-up-tray')->color('gray')
            ->requiresConfirmation()
            ->modalDescription('Runs the uploaded SQL against this database; matching tables are overwritten.')
            ->form([
                Forms\Components\FileUpload::make('file')
                    ->label('SQL file (.sql or .sql.gz)')
                    ->disk('local')->directory('db-imports')->preserveFilenames()->maxSize(262144)->required(),
            ])
            ->action(fn (array $data) => $this->import($data['file']));
    }

    public function checkAction(): Action
    {
        return Action::make('check')->label('Check')->icon('heroicon-o-magnifying-glass')->color('gray')
            ->action(fn () => $this->maintenance('db:check', 'Check'));
    }

    public function repairAction(): Action
    {
        return Action::make('repair')->label('Repair')->icon('heroicon-o-wrench')->color('gray')
            ->requiresConfirmation()->action(fn () => $this->maintenance('db:repair', 'Repair'));
    }

    public function optimizeAction(): Action
    {
        return Action::make('optimize')->label('Optimize')->icon('heroicon-o-bolt')->color('gray')
            ->action(fn () => $this->maintenance('db:optimize', 'Optimize'));
    }

    public function grantUserAction(): Action
    {
        return Action::make('grantUser')->label('Add user')->icon('heroicon-o-user-plus')
            ->form([
                Forms\Components\TextInput::make('username')->required()
                    ->regex('/^[a-z][a-z0-9_]{2,31}$/')
                    ->helperText('3-32 chars: a-z, 0-9, _ — start with a letter.'),
                Forms\Components\Select::make('privileges')
                    ->options(['all' => 'Full access (read/write)', 'readonly' => 'Read-only (SELECT)'])
                    ->default('all')->required(),
                Forms\Components\TextInput::make('password')->password()->revealable()
                    ->minLength(12)->helperText('Leave blank to auto-generate.'),
            ])
            ->action(fn (array $data) => $this->grantUser($data));
    }

    public function resetPasswordAction(): Action
    {
        return Action::make('resetPassword')->label('Reset primary password')
            ->icon('heroicon-o-key')->color('gray')
            ->form([
                Forms\Components\TextInput::make('password')->password()->revealable()
                    ->minLength(12)->helperText('Leave blank to auto-generate.'),
            ])
            ->action(function (array $data) {
                $generated = empty($data['password']);
                $password = $generated ? Str::password(20, symbols: false) : $data['password'];
                $result = app(PanelCtl::class)->run('db:password',
                    ['user' => $this->record->db_user], $password . "\n");
                $this->report($result, "Password reset for {$this->record->db_user}",
                    $generated ? "New password: {$password}" : null);
                if ($result->ok()) {
                    AuditLog::record('db.password', $this->record->db_user);
                }
            });
    }

    // ---- per-user wire actions --------------------------------------------

    public function setPrivilege(int $id, string $level): void
    {
        $user = $this->record->users()->findOrFail($id);
        $level = $level === 'readonly' ? 'readonly' : 'all';
        $result = app(PanelCtl::class)->run('db:user:privileges', [
            'name' => $this->record->name, 'user' => $user->username, 'privileges' => $level,
        ]);
        if ($result->ok()) {
            $user->update(['privileges' => $level]);
            AuditLog::record('db.user.privileges', "{$user->username}: {$level}");
        }
        $this->report($result, $result->output());
    }

    public function revokeUser(int $id): void
    {
        $this->record->users()->findOrFail($id)->delete(); // observer drops the MySQL user
        Notification::make()->title('User access revoked')->success()->send();
    }

    // ---- helpers -----------------------------------------------------------

    private function maintenance(string $command, string $label): void
    {
        $result = app(PanelCtl::class)->run($command, ['name' => $this->record->name]);
        if ($result->ok()) {
            AuditLog::record('db.' . strtolower($label), $this->record->name);
        }
        $this->report($result, "{$label} complete", $result->ok() ? $result->output() : null);
        $this->loadInfo();
    }

    private function grantUser(array $data): void
    {
        $generated = empty($data['password']);
        $password = $generated ? Str::password(20, symbols: false) : $data['password'];

        $result = app(PanelCtl::class)->run('db:user:add', [
            'name' => $this->record->name,
            'user' => $data['username'],
            'privileges' => $data['privileges'],
        ], $password . "\n");

        if (!$result->ok()) {
            $this->report($result, 'Failed to add user');
            return;
        }
        $this->record->users()->create(['username' => $data['username'], 'privileges' => $data['privileges']]);
        AuditLog::record('db.user.add', "{$data['username']} -> {$this->record->name}");
        Notification::make()->title("User {$data['username']} added")
            ->body($generated ? "Password: {$password} — save it now." : null)
            ->success()->persistent()->send();
    }

    private function import(string $stored): void
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
        $result = app(PanelCtl::class)->run('db:import', ['name' => $this->record->name, 'src' => $tmp]);
        @unlink($tmp);
        if ($result->ok()) {
            AuditLog::record('db.import', $this->record->name);
        }
        $this->report($result, $result->output());
        $this->loadInfo();
    }

    private function report(\App\Services\CtlResult $result, string $title, ?string $body = null): void
    {
        Notification::make()->title($result->ok() ? $title : 'Failed')
            ->body($result->ok() ? $body : $result->output())
            ->{$result->ok() ? 'success' : 'danger'}()
            ->persistent(! $result->ok())
            ->send();
    }
}
