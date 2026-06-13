<?php

declare(strict_types=1);

/**
 * Panel self-management: put the panel behind a proper domain with a
 * Let's Encrypt certificate instead of <ip>:8443 + self-signed cert.
 */
final class PanelCommands
{
    private const ACCESS_FILE = '/etc/hostingpanel/panel-access.conf';

    /**
     * Restrict who can reach the panel (port 8443) to an IP allowlist, or
     * open it to everyone. Run with --open over SSH if you ever lock yourself
     * out:  panelctl panel:access --open
     *
     * @param array<string, string> $flags
     */
    public static function access(Ctx $ctx, array $flags): int
    {
        if (($flags['open'] ?? '') === '1') {
            $ctx->deletePath(self::ACCESS_FILE);
            $ctx->run(['systemctl', 'reload', 'apache2'], null, true);
            $ctx->out('Panel access opened to all IP addresses.');
            return 0;
        }

        $raw = $ctx->dryRun ? '[]' : $ctx->stdin();
        $list = json_decode($raw === '' ? '[]' : $raw, true);
        if (!is_array($list) || $list === []) {
            throw new InvalidArgumentException('Provide at least one IP/CIDR (or use --open).');
        }

        $rules = '';
        foreach ($list as $entry) {
            $rules .= '    Require ip ' . self::validCidr((string) $entry) . "\n";
        }
        $ctx->writeFile(
            self::ACCESS_FILE,
            "# Panel access allowlist — managed by HostingPanel.\n"
            . "# Escape hatch over SSH:  panelctl panel:access --open\n"
            . "<RequireAny>\n{$rules}</RequireAny>\n"
        );
        $ctx->run(['systemctl', 'reload', 'apache2'], null, true);
        $ctx->out('Panel access restricted to ' . count($list) . ' address range(s).');
        return 0;
    }

    private static function validCidr(string $entry): string
    {
        $entry = trim($entry);
        $ip = $entry;
        $mask = null;
        if (str_contains($entry, '/')) {
            [$ip, $mask] = explode('/', $entry, 2);
        }
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            throw new InvalidArgumentException("Invalid IP address: {$entry}");
        }
        if ($mask !== null && (!ctype_digit($mask) || (int) $mask > 128)) {
            throw new InvalidArgumentException("Invalid CIDR mask: {$entry}");
        }
        return $entry;
    }

    public static function domain(Ctx $ctx, array $flags): int
    {
        $domain = Validate::domain($flags['domain'] ?? '');
        $acmeRoot = '/var/www/panel-acme';

        // Port-80 vhost so the HTTP-01 challenge can complete
        if (!$ctx->dryRun && !is_dir($acmeRoot)) {
            mkdir($acmeRoot, 0755, true);
        }
        $ctx->writeFile(
            '/etc/apache2/sites-available/panel-acme.conf',
            "<VirtualHost *:80>\n    ServerName {$domain}\n    DocumentRoot {$acmeRoot}\n"
            . "    <Directory {$acmeRoot}>\n        Require all granted\n    </Directory>\n</VirtualHost>\n"
        );
        $ctx->run(['a2ensite', '--quiet', 'panel-acme.conf']);
        $ctx->run(['systemctl', 'reload', 'apache2']);

        $argv = [
            'certbot', 'certonly', '--webroot', '-w', $acmeRoot, '-d', $domain,
            '--non-interactive', '--agree-tos',
            '--deploy-hook', 'systemctl reload apache2',
        ];
        SslCommands::emailArgs($argv);
        $ctx->run($argv);

        // Re-render the panel vhost pointing at the LE certificate
        $ctx->writeFile(
            '/etc/apache2/sites-available/hostingpanel.conf',
            $ctx->template('panel-vhost.conf.tpl', [
                'server_name' => $domain,
                'cert' => "/etc/letsencrypt/live/{$domain}/fullchain.pem",
                'key' => "/etc/letsencrypt/live/{$domain}/privkey.pem",
            ])
        );
        $ctx->run(['systemctl', 'reload', 'apache2']);

        $ctx->out("Panel is now available at https://{$domain}:8443 with a trusted certificate.");
        return 0;
    }

    /**
     * Pull the latest code from the git source clone and redeploy.
     * Keeps phpMyAdmin/webmail/vendor/var untouched; the panel's own
     * FPM pool is reloaded gracefully so the triggering request survives.
     */
    public static function selfUpdate(Ctx $ctx, array $flags): int
    {
        $src = '/opt/hostingpanel-src';
        $dst = '/opt/hostingpanel';

        if (!$ctx->dryRun && !is_dir($src . '/.git')) {
            $repoFile = '/etc/hostingpanel/repo-url';
            if (!is_file($repoFile)) {
                throw new RuntimeException(
                    "No git clone at {$src} and no /etc/hostingpanel/repo-url. "
                    . "Clone your repo there once: git clone <repo> {$src}"
                );
            }
            $repo = trim((string) file_get_contents($repoFile));
            $ctx->run(['git', 'clone', '--depth', '1', $repo, $src]);
        } else {
            $ctx->run(['git', '-C', $src, 'pull', '--ff-only']);
        }

        $ctx->run([
            'rsync', '-a', '--delete',
            '--exclude', 'web/vendor', '--exclude', 'web/node_modules', '--exclude', '.git',
            '--exclude', 'web/.env', '--exclude', 'web/storage', '--exclude', 'panel',
            '--exclude', 'phpmyadmin', '--exclude', 'webmail',
            $src . '/', $dst . '/',
        ]);

        $web = $dst . '/web';
        $ctx->run(['bash', '-c',
            "cd {$web} && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction --quiet"]);
        // Re-apply ownership of writable dirs, run migrations + rebuild caches.
        $ctx->run(['chown', '-R', 'hostingpanel:hostingpanel', "{$web}/storage", "{$web}/bootstrap/cache"], null, true);
        $ctx->run(['bash', '-c',
            "cd {$web} && sudo -u hostingpanel php artisan migrate --force --no-interaction"]);
        $ctx->run(['bash', '-c',
            "cd {$web} && php artisan filament:assets && php artisan optimize"]);
        // Keep phpMyAdmin SSO config in sync (idempotent; reuses the password).
        PmaCommands::setup($ctx, []);
        $ctx->run(['chown', '-R', 'hostingpanel:hostingpanel', "{$web}/storage", "{$web}/bootstrap/cache"], null, true);

        $ctx->run(['install', '-m', '755', "{$dst}/scripts/backup.sh", '/usr/local/bin/hostingpanel-backup']);
        $ctx->run(['install', '-m', '755', "{$dst}/scripts/hp-acme-auth.sh", '/usr/local/bin/hp-acme-auth'], null, true);
        $ctx->run(['install', '-m', '755', "{$dst}/scripts/hp-acme-cleanup.sh", '/usr/local/bin/hp-acme-cleanup'], null, true);
        // Refresh the daemon unit in case it changed, then reload systemd.
        $ctx->run(['install', '-m', '644', "{$dst}/etc/panelctld.service", '/etc/systemd/system/panelctld.service'], null, true);
        $ctx->run(['systemctl', 'daemon-reload'], null, true);
        $ctx->run(['systemctl', 'reload', 'php8.3-fpm'], null, true);
        $ctx->run(['systemctl', 'reload', 'apache2'], null, true);

        // Restart panelctld AFTER this request finishes (a synchronous restart
        // would kill the process currently running this self-update). systemd-run
        // schedules it in a transient unit outside our cgroup.
        $ctx->run(['systemd-run', '--on-active=3', 'systemctl', 'restart', 'panelctld'], null, true);

        $rev = $ctx->dryRun ? 'dry-run' : trim($ctx->run(['git', '-C', $src, 'rev-parse', '--short', 'HEAD'], null, true));
        $ctx->out("Panel updated to revision {$rev}. The privileged daemon restarts in a few seconds.");
        return 0;
    }
}
