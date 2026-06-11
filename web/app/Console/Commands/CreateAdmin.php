<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateAdmin extends Command
{
    protected $signature = 'hostingpanel:admin
        {email : Admin email / login}
        {--password= : Password (generated if omitted)}
        {--name=Administrator}';

    protected $description = 'Create or reset the panel admin user';

    public function handle(): int
    {
        $email = $this->argument('email');
        $password = $this->option('password') ?: Str::password(20);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email address.');
            return self::FAILURE;
        }

        $existing = User::where('email', $email)->exists();
        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $this->option('name'),
                'password' => Hash::make($password),
                'totp_secret' => null,
                'totp_enabled' => false,
            ],
        );

        $this->info($existing ? 'Admin password reset (2FA disabled).' : 'Admin user created.');
        $this->line("  Email:    {$email}");
        $this->line("  Password: {$password}");
        return self::SUCCESS;
    }
}
