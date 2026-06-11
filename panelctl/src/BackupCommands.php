<?php

declare(strict_types=1);

/**
 * Automatic backups (site files + all MySQL databases + panel data)
 * to Google Drive or an FTP server via rclone. Credentials arrive as
 * JSON on stdin and live only in /etc/hostingpanel/rclone.conf (0600).
 */
final class BackupCommands
{
    private const RCLONE_CONF = '/etc/hostingpanel/rclone.conf';
    private const ENV_FILE = '/etc/hostingpanel/backup.env';
    private const CRON_FILE = '/etc/cron.d/hostingpanel-backup';
    private const SCRIPT = '/usr/local/bin/hostingpanel-backup';
    private const LOG = '/var/log/hostingpanel/backup.log';

    /** Configure the rclone remote. stdin JSON: {type, host, user, pass, token, path, retention} */
    public static function config(Ctx $ctx, array $flags): int
    {
        $raw = $ctx->dryRun ? '{}' : (string) stream_get_contents(STDIN);
        $cfg = json_decode($raw === '' ? '{}' : $raw, true);
        if (!is_array($cfg)) {
            throw new InvalidArgumentException('Expected JSON config on stdin.');
        }

        $type = (string) ($cfg['type'] ?? '');
        $path = (string) ($cfg['path'] ?? 'hostingpanel-backups');
        $retention = (int) ($cfg['retention'] ?? 7);
        if (!preg_match('#^[A-Za-z0-9._/-]{1,128}$#', $path)) {
            throw new InvalidArgumentException('Invalid remote path.');
        }
        if ($retention < 1 || $retention > 365) {
            throw new InvalidArgumentException('Retention must be 1-365.');
        }

        if ($type === 'ftp') {
            $host = (string) ($cfg['host'] ?? '');
            $user = (string) ($cfg['user'] ?? '');
            $pass = (string) ($cfg['pass'] ?? '');
            if (!preg_match('/^[a-zA-Z0-9.-]{3,253}$/', $host) || $user === '' || $pass === '') {
                throw new InvalidArgumentException('FTP host, user and password are required.');
            }
            $obscured = $ctx->dryRun ? 'xxx' : trim($ctx->run(['rclone', 'obscure', '-'], $pass));
            $conf = "[backup]\ntype = ftp\nhost = {$host}\nuser = {$user}\npass = {$obscured}\n"
                . "explicit_tls = true\n";
        } elseif ($type === 'drive') {
            $token = trim((string) ($cfg['token'] ?? ''));
            if ($token === '' || json_decode($token) === null) {
                throw new InvalidArgumentException(
                    'Google Drive needs the token JSON from: rclone authorize "drive" (run on your PC).'
                );
            }
            $conf = "[backup]\ntype = drive\nscope = drive.file\ntoken = {$token}\n";
        } else {
            throw new InvalidArgumentException('Type must be "ftp" or "drive".');
        }

        $ctx->writeFile(self::RCLONE_CONF, $conf, 0600);
        $ctx->writeFile(self::ENV_FILE, "REMOTE_PATH={$path}\nRETENTION={$retention}\n", 0600);
        $ctx->out("Backup remote configured ({$type}, path {$path}, keep {$retention}).");
        return 0;
    }

    /** Write or remove the backup cron entry. */
    public static function schedule(Ctx $ctx, array $flags): int
    {
        if (($flags['disable'] ?? '') === '1') {
            $ctx->deletePath(self::CRON_FILE);
            $ctx->out('Scheduled backups disabled.');
            return 0;
        }
        $cron = CronCommands::validSchedule($flags['cron'] ?? '');
        $ctx->writeFile(
            self::CRON_FILE,
            "# Managed by HostingPanel\n{$cron} root " . self::SCRIPT . ' >> ' . self::LOG . " 2>&1\n"
        );
        $ctx->out("Backups scheduled: {$cron}");
        return 0;
    }

    /** Verify the remote is reachable. */
    public static function test(Ctx $ctx, array $flags): int
    {
        if (!$ctx->dryRun && !is_file(self::RCLONE_CONF)) {
            throw new RuntimeException('No backup remote configured yet.');
        }
        $ctx->run(['rclone', '--config', self::RCLONE_CONF, '--contimeout', '15s', 'mkdir', 'backup:hostingpanel-test']);
        $ctx->run(['rclone', '--config', self::RCLONE_CONF, 'rmdir', 'backup:hostingpanel-test'], null, true);
        $ctx->out('Connection OK — remote is writable.');
        return 0;
    }

    /** Kick off a backup in the background (can take minutes). */
    public static function run(Ctx $ctx, array $flags): int
    {
        if (!$ctx->dryRun && !is_file(self::RCLONE_CONF)) {
            throw new RuntimeException('No backup remote configured yet.');
        }
        $ctx->run(['bash', '-c', 'nohup ' . self::SCRIPT . ' >> ' . self::LOG . ' 2>&1 < /dev/null &']);
        $ctx->out('Backup started in the background — check the log on the Settings page.');
        return 0;
    }

    /** Show the last lines of the backup log. */
    public static function log(Ctx $ctx, array $flags): int
    {
        if ($ctx->dryRun) {
            $ctx->out('[dry-run] backup log');
            return 0;
        }
        if (!is_file(self::LOG)) {
            $ctx->out('No backups have run yet.');
            return 0;
        }
        $ctx->out(trim($ctx->run(['tail', '-n', '40', self::LOG], null, true)));
        return 0;
    }
}
