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
    private const EXPORTS = '/var/lib/hostingpanel/exports';

    /**
     * Build a downloadable backup archive and print its path.
     *   type=full  — every database dump + every site + panel data (one .tar.gz)
     *   type=site  — a single site's files (--domain)
     * @param array<string, string> $flags
     */
    public static function download(Ctx $ctx, array $flags): int
    {
        $type = ($flags['type'] ?? 'full') === 'site' ? 'site' : 'full';
        $stamp = date('Ymd-His');
        $domain = $type === 'site' ? Validate::domain($flags['domain'] ?? '') : '';
        $file = $type === 'site'
            ? self::EXPORTS . "/site-{$domain}-{$stamp}.tar.gz"
            : self::EXPORTS . "/hostingpanel-full-{$stamp}.tar.gz";

        if ($ctx->dryRun) {
            $ctx->out($file);
            return 0;
        }
        if (!is_dir(self::EXPORTS)) {
            mkdir(self::EXPORTS, 0750, true);
        }
        $ctx->run(['chown', 'hostingpanel:hostingpanel', self::EXPORTS], null, true);
        $ef = escapeshellarg($file);

        if ($type === 'site') {
            $ctx->run(['bash', '-c', 'tar czf ' . $ef . ' -C /var/www ' . escapeshellarg($domain)]);
        } else {
            $script = 'set -e; W=$(mktemp -d); '
                . 'for db in $(mysql -N -e "SHOW DATABASES" | grep -Ev "^(information_schema|performance_schema|mysql|sys)$"); do '
                . 'mysqldump --single-transaction --quick --routines "$db" | gzip > "$W/db-$db.sql.gz"; done; '
                . 'for d in /var/www/*/; do s=$(basename "$d"); [ "$s" = "html" ] && continue; [ "$s" = "panel-acme" ] && continue; '
                . 'tar czf "$W/site-$s.tar.gz" -C /var/www "$s"; done; '
                . 'cp /var/lib/hostingpanel/panel.sqlite "$W/" 2>/dev/null || true; '
                . 'tar czf ' . $ef . ' -C "$W" .; rm -rf "$W"';
            $ctx->run(['bash', '-c', $script]);
        }
        $ctx->run(['chown', 'hostingpanel:hostingpanel', $file], null, true);
        $ctx->run(['chmod', '640', $file], null, true);
        $ctx->out($file);
        return 0;
    }

    /** Restore databases + site files from a staged full-backup upload. */
    public static function restore(Ctx $ctx, array $flags): int
    {
        $src = $flags['src'] ?? '';
        if ($ctx->dryRun) {
            $ctx->out('[dry-run] restore from upload');
            return 0;
        }
        $real = realpath($src);
        if ($real === false || !str_starts_with($real, '/var/lib/hostingpanel/uploads/')) {
            throw new InvalidArgumentException('Restore source must be a staged upload.');
        }
        $er = escapeshellarg($real);
        $script = 'set -e; W=$(mktemp -d); tar xzf ' . $er . ' -C "$W"; '
            . 'for f in "$W"/db-*.sql.gz; do [ -e "$f" ] || continue; n=$(basename "$f"); db=${n#db-}; db=${db%.sql.gz}; '
            . 'mysql -e "CREATE DATABASE IF NOT EXISTS $db"; gunzip -c "$f" | mysql "$db"; done; '
            . 'for f in "$W"/site-*.tar.gz; do [ -e "$f" ] || continue; tar xzpf "$f" -C /var/www; done; '
            . 'rm -rf "$W"';
        $ctx->run(['bash', '-c', $script]);
        @unlink($real);
        $ctx->out('Restore complete — databases imported and site files extracted. Re-check site PHP pools if needed.');
        return 0;
    }

    /** Configure the rclone remote. stdin JSON: {type, host, user, pass, token, path, retention} */
    public static function config(Ctx $ctx, array $flags): int
    {
        $raw = $ctx->dryRun ? '{}' : $ctx->stdin();
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
