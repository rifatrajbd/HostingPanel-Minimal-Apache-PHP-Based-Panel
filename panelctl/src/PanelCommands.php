<?php

declare(strict_types=1);

/**
 * Panel self-management: put the panel behind a proper domain with a
 * Let's Encrypt certificate instead of <ip>:8443 + self-signed cert.
 */
final class PanelCommands
{
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
        $ctx->run(['chown', '-R', 'hostingpanel:hostingpanel', "{$web}/storage", "{$web}/bootstrap/cache"], null, true);

        $ctx->run(['install', '-m', '755', "{$dst}/scripts/backup.sh", '/usr/local/bin/hostingpanel-backup']);
        $ctx->run(['install', '-m', '440', "{$dst}/etc/sudoers.d/hostingpanel", '/etc/sudoers.d/hostingpanel']);
        $ctx->run(['visudo', '-c'], null, false);
        $ctx->run(['systemctl', 'reload', 'php8.3-fpm'], null, true);
        $ctx->run(['systemctl', 'reload', 'apache2'], null, true);

        $rev = $ctx->dryRun ? 'dry-run' : trim($ctx->run(['git', '-C', $src, 'rev-parse', '--short', 'HEAD'], null, true));
        $ctx->out("Panel updated to revision {$rev}.");
        return 0;
    }
}
