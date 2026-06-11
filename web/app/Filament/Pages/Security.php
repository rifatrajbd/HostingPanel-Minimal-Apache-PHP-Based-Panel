<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Support\Totp;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Hash;

class Security extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationGroup = 'System';
    protected static ?int $navigationSort = 4;
    protected static ?string $title = 'Security';

    protected static string $view = 'filament.pages.security';

    public ?array $passwordData = [];
    public ?string $enrollSecret = null;
    public ?string $enrollUri = null;
    public ?string $confirmCode = null;
    public ?string $disablePassword = null;

    public function mount(): void
    {
        $this->passwordForm->fill();
    }

    protected function getForms(): array
    {
        return ['passwordForm'];
    }

    public function passwordForm(Form $form): Form
    {
        return $form
            ->statePath('passwordData')
            ->schema([
                Forms\Components\Section::make('Change password')->schema([
                    Forms\Components\TextInput::make('current')->password()->required()->label('Current password'),
                    Forms\Components\TextInput::make('new')->password()->required()->minLength(12)->label('New password'),
                    Forms\Components\TextInput::make('confirm')->password()->required()->same('new')->label('Repeat new password'),
                ]),
            ]);
    }

    public function changePassword(): void
    {
        $data = $this->passwordForm->getState();
        $user = auth()->user();
        if (!Hash::check($data['current'], $user->password)) {
            Notification::make()->title('Current password is incorrect')->danger()->send();
            return;
        }
        $user->update(['password' => Hash::make($data['new'])]);
        AuditLog::record('user.password_change');
        $this->passwordForm->fill();
        Notification::make()->title('Password updated')->success()->send();
    }

    public function startEnroll(): void
    {
        $this->enrollSecret = Totp::generateSecret();
        $this->enrollUri = Totp::uri($this->enrollSecret, auth()->user()->email, 'HostingPanel');
    }

    public function confirmEnroll(): void
    {
        if (!$this->enrollSecret || !Totp::verify($this->enrollSecret, (string) $this->confirmCode)) {
            Notification::make()->title('Code did not match — try again')->danger()->send();
            return;
        }
        auth()->user()->update(['totp_secret' => $this->enrollSecret, 'totp_enabled' => true]);
        AuditLog::record('user.2fa_enable');
        $this->reset(['enrollSecret', 'enrollUri', 'confirmCode']);
        Notification::make()->title('Two-factor authentication enabled')->success()->send();
    }

    public function disable2fa(): void
    {
        if (!Hash::check((string) $this->disablePassword, auth()->user()->password)) {
            Notification::make()->title('Password incorrect — 2FA unchanged')->danger()->send();
            return;
        }
        auth()->user()->update(['totp_secret' => null, 'totp_enabled' => false]);
        AuditLog::record('user.2fa_disable');
        $this->reset(['disablePassword']);
        Notification::make()->title('Two-factor authentication disabled')->success()->send();
    }

    public function getAuditLogsProperty()
    {
        return AuditLog::latest()->limit(25)->get();
    }
}
