<?php

declare(strict_types=1);

final class SiteCommands
{
    /** @param array<string, string> $flags */
    public static function create(Ctx $ctx, array $flags): int
    {
        $domain = Validate::domain($flags['domain'] ?? '');
        $php = Validate::phpVersion($flags['php'] ?? '');
        $user = Validate::systemUserFor($domain);
        $home = "/var/www/{$domain}";
        $docRoot = "{$home}/htdocs";
        $socket = "/run/php/{$domain}.sock";

        // System user (no shell, home = site dir). Retry on transient
        // /etc/passwd lock contention from background apt jobs.
        $ctx->run([
            'useradd', '--create-home', '--home-dir', $home,
            '--shell', '/usr/sbin/nologin', '--user-group', $user,
        ], null, false, null, true);

        if (!$ctx->dryRun) {
            foreach (["{$docRoot}", "{$home}/logs", "{$home}/tmp"] as $dir) {
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
            }
        }

        // PHP-FPM pool
        self::writePool($ctx, $domain, $php, []);

        // Apache vhost
        $ctx->writeFile(
            "/etc/apache2/sites-available/{$domain}.conf",
            $ctx->template('vhost.conf.tpl', [
                'domain' => $domain,
                'doc_root' => $docRoot,
                'socket' => $socket,
                'home' => $home,
            ])
        );

        // Placeholder page
        $ctx->writeFile(
            "{$docRoot}/index.php",
            "<?php http_response_code(200); ?>\n<!DOCTYPE html><html><head><title>"
            . htmlspecialchars($domain)
            . "</title></head><body style=\"font-family:sans-serif;background:#0f172a;color:#e2e8f0;"
            . "display:flex;align-items:center;justify-content:center;height:100vh;margin:0\">"
            . "<div style=\"text-align:center\"><h1>" . htmlspecialchars($domain) . "</h1>"
            . "<p>Site created with HostingPanel — upload your files to htdocs/.</p></div></body></html>\n"
        );

        $ctx->run(['chown', '-R', "{$user}:{$user}", $home]);
        $ctx->run(['a2ensite', '--quiet', "{$domain}.conf"]);
        $ctx->run(['systemctl', 'restart', "php{$php}-fpm"]);
        $ctx->run(['systemctl', 'reload', 'apache2']);

        $ctx->out("Site {$domain} created: docroot {$docRoot}, PHP {$php} pool as user {$user}.");
        return 0;
    }

    /** @param array<string, string> $flags */
    public static function delete(Ctx $ctx, array $flags): int
    {
        $domain = Validate::domain($flags['domain'] ?? '');
        $user = Validate::systemUserFor($domain);

        $ctx->run(['a2dissite', '--quiet', "{$domain}.conf"], null, true);
        $ctx->run(['a2dissite', '--quiet', "{$domain}-le-ssl.conf"], null, true);
        $ctx->deletePath("/etc/apache2/sites-available/{$domain}.conf");
        $ctx->deletePath("/etc/apache2/sites-available/{$domain}-le-ssl.conf");

        foreach (PANELCTL_PHP_VERSIONS as $version) {
            $pool = "/etc/php/{$version}/fpm/pool.d/{$domain}.conf";
            if ($ctx->dryRun || is_file($pool)) {
                $ctx->deletePath($pool);
                $ctx->run(['systemctl', 'restart', "php{$version}-fpm"], null, true);
            }
        }

        $ctx->run(['systemctl', 'reload', 'apache2'], null, true);
        $ctx->run(['certbot', 'delete', '--non-interactive', '--cert-name', $domain], null, true);
        $ctx->run(['userdel', $user], null, true, null, true);
        $ctx->deletePath("/var/www/{$domain}");

        $ctx->out("Site {$domain} deleted (files, vhost, FPM pool, certificate).");
        return 0;
    }

    /**
     * Render a site's FPM pool config (shared by site:create, site:php
     * and site:ini). $ini overrides are merged over the defaults.
     *
     * @param array<string, string> $ini
     */
    public static function writePool(Ctx $ctx, string $domain, string $php, array $ini): void
    {
        $user = Validate::systemUserFor($domain);
        $home = "/var/www/{$domain}";
        $values = array_merge(PhpCommands::INI_DEFAULTS, $ini);

        $ctx->writeFile(
            "/etc/php/{$php}/fpm/pool.d/{$domain}.conf",
            $ctx->template('fpm-pool.conf.tpl', [
                'domain' => $domain,
                'user' => $user,
                'socket' => "/run/php/{$domain}.sock",
                'home' => $home,
                'memory_limit' => $values['memory_limit'],
                'upload_max_filesize' => $values['upload_max_filesize'],
                'post_max_size' => $values['post_max_size'],
                'max_execution_time' => $values['max_execution_time'],
                'display_errors' => $values['display_errors'],
            ])
        );
    }
}
