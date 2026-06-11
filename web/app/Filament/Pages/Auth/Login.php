<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use App\Support\Totp;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        $this->getEmailFormComponent(),
                        $this->getPasswordFormComponent(),
                        $this->getCodeFormComponent(),
                        $this->getRememberFormComponent(),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    protected function getCodeFormComponent(): Component
    {
        return TextInput::make('code')
            ->label('Two-factor code')
            ->helperText('Only required if 2FA is enabled on your account.')
            ->numeric()
            ->autocomplete('one-time-code');
    }

    /**
     * Verify the TOTP code (when enabled) before the parent completes login.
     */
    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.email' => __('filament-panels::pages/auth/login.messages.failed'),
        ]);
    }

    public function authenticate(): ?\Filament\Http\Responses\Auth\Contracts\LoginResponse
    {
        $data = $this->form->getState();

        $user = User::where('email', $data['email'] ?? '')->first();
        if ($user && $user->totp_enabled) {
            $code = (string) ($data['code'] ?? '');
            if (!$user->totp_secret || !Totp::verify($user->totp_secret, $code)) {
                throw ValidationException::withMessages([
                    'data.code' => 'Invalid or missing two-factor code.',
                ]);
            }
        }

        return parent::authenticate();
    }
}
