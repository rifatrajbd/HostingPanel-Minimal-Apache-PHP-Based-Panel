<?php

/**
 * Create (or reset) the panel admin user.
 *
 *   php bin/create-admin.php <username> [password]
 *
 * If no password is given, a strong one is generated and printed.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Panel\Database;
use Panel\Support\Validator;

$settings = require dirname(__DIR__) . '/config/settings.php';

$username = $argv[1] ?? 'admin';
$password = $argv[2] ?? Validator::randomPassword(20);

if (!preg_match('/^[a-zA-Z0-9_.-]{3,32}$/', $username)) {
    fwrite(STDERR, "Invalid username (3-32 chars: letters, digits, _ . -)\n");
    exit(1);
}
if (strlen($password) < 12) {
    fwrite(STDERR, "Password must be at least 12 characters.\n");
    exit(1);
}

$db = new Database($settings['db_path']);
$db->migrate();

$hash = password_hash($password, PASSWORD_ARGON2ID);
$existing = $db->one('SELECT id FROM users WHERE username = ?', [$username]);

if ($existing !== null) {
    $db->run(
        'UPDATE users SET password_hash = ?, totp_secret = NULL, totp_enabled = 0 WHERE id = ?',
        [$hash, $existing['id']]
    );
    echo "Password reset for existing user (2FA disabled).\n";
} else {
    $db->run(
        'INSERT INTO users (username, password_hash, created_at) VALUES (?, ?, ?)',
        [$username, $hash, time()]
    );
    echo "Admin user created.\n";
}

echo "  Username: {$username}\n";
echo "  Password: {$password}\n";
