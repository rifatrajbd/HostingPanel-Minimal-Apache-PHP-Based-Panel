<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use App\Support\Totp;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Facades\Filament;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    /** True once the password is accepted and we're asking for the 2FA code. */
    public bool $challenge = false;

    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        $this->getEmailFormComponent()->visible(fn () => ! $this->challenge),
                        $this->getPasswordFormComponent()->visible(fn () => ! $this->challenge),
                        $this->getCodeFormComponent()
                            ->visible(fn () => $this->challenge)
                            ->required(fn () => $this->challenge),
                        $this->getRememberFormComponent()->visible(fn () => ! $this->challenge),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    protected function getCodeFormComponent(): Component
    {
        return TextInput::make('code')
            ->label('Two-factor code')
            ->helperText('Enter the 6-digit code from your authenticator app.')
            ->numeric()
            ->autocomplete('one-time-code')
            ->autofocus()
            ->extraInputAttributes(['inputmode' => 'numeric', 'maxlength' => 6]);
    }

    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            Notification::make()
                ->title('Too many attempts')
                ->body("Try again in {$exception->secondsUntilAvailable} seconds.")
                ->danger()->send();
            return null;
        }

        $data = $this->form->getState();
        $user = User::where('email', $data['email'] ?? '')->first();

        // --- Step 1: verify the password (do NOT log in yet) ----------------
        if (! $this->challenge) {
            if (! $user || ! Hash::check($data['password'] ?? '', $user->password)) {
                // Fire the Failed event so the fail2ban auth log records it.
                event(new \Illuminate\Auth\Events\Failed(
                    Filament::auth()->getDefaultDriver(),
                    $user,
                    ['email' => $data['email'] ?? ''],
                ));
                $this->throwFailureValidationException();
            }
            if ($user->totp_enabled) {
                $this->challenge = true; // re-render: now show the code field
                return null;
            }
            return $this->completeLogin($user, (bool) ($data['remember'] ?? false));
        }

        // --- Step 2: verify the 2FA code -----------------------------------
        if (! $user || ! $user->totp_secret || ! Totp::verify($user->totp_secret, (string) ($data['code'] ?? ''))) {
            throw ValidationException::withMessages([
                'data.code' => 'Invalid two-factor code.',
            ]);
        }
        return $this->completeLogin($user, (bool) ($data['remember'] ?? false));
    }

    private function completeLogin(User $user, bool $remember): LoginResponse
    {
        Filament::auth()->login($user, $remember);
        session()->regenerate();

        return app(LoginResponse::class);
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.email' => __('filament-panels::pages/auth/login.messages.failed'),
        ]);
    }
}
