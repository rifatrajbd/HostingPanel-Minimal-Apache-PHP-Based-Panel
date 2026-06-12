<?php

declare(strict_types=1);

/**
 * SFTP account management (secure file transfer over SSH — no plaintext FTP).
 *
 * An account is a system user with no shell, chrooted by sshd to its site's
 * directory (see the "sftponly" Match block installed in sshd_config). The
 * account's primary group is the site group, so it can write the setgid
 * htdocs directory; its supplementary "sftponly" group triggers the chroot.
 */
final class FtpCommands
{
    private const RESERVED = [
        'root', 'daemon', 'bin', 'sys', 'sync', 'www-data', 'hostingpanel',
        'vmail', 'mysql', 'postfix', 'dovecot', 'sshd', 'nobody', '_rspamd',
    ];

    /** @param array<string, string> $flags */
    public static function create(Ctx $ctx, array $flags): int
    {
        $user = self::validUser($flags['username'] ?? '');
        $domain = Validate::domain($flags['domain'] ?? '');
        $siteGroup = Validate::systemUserFor($domain);
        $home = "/var/www/{$domain}";
        $password = self::readPassword($ctx);

        if (!$ctx->dryRun && !is_dir($home)) {
            throw new RuntimeException("Site directory {$home} does not exist.");
        }

        // Primary group = the site group (write access to setgid htdocs);
        // supplementary group sftponly = sshd chroots the account.
        $ctx->run([
            'useradd', '--no-create-home', '--home-dir', $home,
            '--shell', '/usr/sbin/nologin', '--gid', $siteGroup, '--groups', 'sftponly', $user,
        ], null, false, null, true);
        $ctx->run(['chpasswd'], "{$user}:{$password}\n");

        $ctx->out("SFTP account {$user} created — connects over SSH (port 22), chrooted to {$home}, lands in htdocs/.");
        return 0;
    }

    /** @param array<string, string> $flags */
    public static function password(Ctx $ctx, array $flags): int
    {
        $user = self::validUser($flags['username'] ?? '');
        $password = self::readPassword($ctx);
        $ctx->run(['chpasswd'], "{$user}:{$password}\n");
        $ctx->out("Password updated for SFTP account {$user}.");
        return 0;
    }

    /** @param array<string, string> $flags */
    public static function delete(Ctx $ctx, array $flags): int
    {
        $user = self::validUser($flags['username'] ?? '');
        $ctx->run(['userdel', $user], null, true, null, true);
        $ctx->out("SFTP account {$user} removed (site files kept).");
        return 0;
    }

    private static function validUser(string $user): string
    {
        $user = strtolower(trim($user));
        if (!preg_match('/^[a-z][a-z0-9_-]{2,31}$/', $user)) {
            throw new InvalidArgumentException('Username must be 3-32 chars: a-z, 0-9, _ or -, starting with a letter.');
        }
        if (in_array($user, self::RESERVED, true) || str_starts_with($user, 'web-')) {
            throw new InvalidArgumentException('That username is reserved.');
        }
        return $user;
    }

    private static function readPassword(Ctx $ctx): string
    {
        $password = $ctx->readSecret();
        if (strlen($password) < 8 || strlen($password) > 128 || str_contains($password, "\n")) {
            throw new InvalidArgumentException('Password must be 8-128 characters.');
        }
        return $password;
    }
}
