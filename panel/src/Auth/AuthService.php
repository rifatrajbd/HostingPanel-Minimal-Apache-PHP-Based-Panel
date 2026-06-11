<?php

declare(strict_types=1);

namespace Panel\Auth;

use Panel\Database;
use Panel\Support\Totp;

final class AuthService
{
    /** @param array<string, mixed> $settings */
    public function __construct(
        private readonly Database $db,
        private readonly array $settings
    ) {
    }

    /**
     * @return string 'ok' | 'totp' | 'invalid' | 'throttled'
     */
    public function attempt(string $username, string $password, string $ip): string
    {
        if ($this->isThrottled($username, $ip)) {
            return 'throttled';
        }

        $user = $this->db->one('SELECT * FROM users WHERE username = ?', [$username]);

        $valid = $user !== null && password_verify($password, (string) $user['password_hash']);
        $this->recordAttempt($username, $ip, $valid);

        if (!$valid) {
            // Constant-ish time: hash something even when the user doesn't exist.
            if ($user === null) {
                password_verify($password, password_hash('dummy', PASSWORD_ARGON2ID));
            }
            return 'invalid';
        }

        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_ARGON2ID)) {
            $this->db->run(
                'UPDATE users SET password_hash = ? WHERE id = ?',
                [password_hash($password, PASSWORD_ARGON2ID), $user['id']]
            );
        }

        if ((int) $user['totp_enabled'] === 1) {
            session_regenerate_id(true);
            $_SESSION['pending_user_id'] = (int) $user['id'];
            return 'totp';
        }

        $this->establishSession((int) $user['id']);
        return 'ok';
    }

    public function completeTotp(string $code, string $ip): bool
    {
        $pendingId = $_SESSION['pending_user_id'] ?? null;
        if (!is_int($pendingId)) {
            return false;
        }
        $user = $this->db->one('SELECT * FROM users WHERE id = ?', [$pendingId]);
        if ($user === null || empty($user['totp_secret'])) {
            return false;
        }
        if ($this->isThrottled((string) $user['username'], $ip)) {
            return false;
        }
        $ok = Totp::verify((string) $user['totp_secret'], $code);
        $this->recordAttempt((string) $user['username'], $ip, $ok);
        if (!$ok) {
            return false;
        }
        unset($_SESSION['pending_user_id']);
        $this->establishSession((int) $user['id']);
        return true;
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $p['path'],
                'secure' => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'],
            ]);
        }
        session_destroy();
    }

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        $id = $_SESSION['user_id'] ?? null;
        if (!is_int($id)) {
            return null;
        }
        return $this->db->one('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public function changePassword(int $userId, string $current, string $new): bool
    {
        $user = $this->db->one('SELECT * FROM users WHERE id = ?', [$userId]);
        if ($user === null || !password_verify($current, (string) $user['password_hash'])) {
            return false;
        }
        $this->db->run(
            'UPDATE users SET password_hash = ? WHERE id = ?',
            [password_hash($new, PASSWORD_ARGON2ID), $userId]
        );
        session_regenerate_id(true);
        return true;
    }

    public function startTotpEnrollment(int $userId): string
    {
        $secret = Totp::generateSecret();
        $_SESSION['totp_enroll_secret'] = $secret;
        return $secret;
    }

    public function confirmTotpEnrollment(int $userId, string $code): bool
    {
        $secret = $_SESSION['totp_enroll_secret'] ?? null;
        if (!is_string($secret) || !Totp::verify($secret, $code)) {
            return false;
        }
        $this->db->run(
            'UPDATE users SET totp_secret = ?, totp_enabled = 1 WHERE id = ?',
            [$secret, $userId]
        );
        unset($_SESSION['totp_enroll_secret']);
        session_regenerate_id(true);
        return true;
    }

    public function disableTotp(int $userId, string $password): bool
    {
        $user = $this->db->one('SELECT * FROM users WHERE id = ?', [$userId]);
        if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
            return false;
        }
        $this->db->run(
            'UPDATE users SET totp_secret = NULL, totp_enabled = 0 WHERE id = ?',
            [$userId]
        );
        return true;
    }

    private function establishSession(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['login_at'] = time();
    }

    private function isThrottled(string $username, string $ip): bool
    {
        $since = time() - (int) $this->settings['login_window_sec'];
        $max = (int) $this->settings['login_max_attempts'];
        $row = $this->db->one(
            'SELECT COUNT(*) AS n FROM login_attempts
             WHERE success = 0 AND created_at > ? AND (ip = ? OR username = ?)',
            [$since, $ip, $username]
        );
        return $row !== null && (int) $row['n'] >= $max;
    }

    private function recordAttempt(string $username, string $ip, bool $success): void
    {
        $this->db->run(
            'INSERT INTO login_attempts (ip, username, success, created_at) VALUES (?, ?, ?, ?)',
            [$ip, $username, $success ? 1 : 0, time()]
        );
        // Opportunistic cleanup of old rows.
        $this->db->run('DELETE FROM login_attempts WHERE created_at < ?', [time() - 86400]);
    }
}
