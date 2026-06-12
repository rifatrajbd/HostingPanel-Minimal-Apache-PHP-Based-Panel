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

        // System user (no shell). Home is the site dir but it is NOT created
        // by useradd — the dir itself stays root-owned so it can serve as an
        // SFTP chroot. Retry on transient /etc/passwd lock contention.
        //
        // Idempotent: the panel only calls site:create when it has no record
        // of this domain, so a pre-existing user is an orphan from a failed
        // attempt — reuse it instead of failing, so retries always succeed.
        if ($ctx->dryRun || !self::userExists($user)) {
            $ctx->run([
                'useradd', '--no-create-home', '--home-dir', $home,
                '--shell', '/usr/sbin/nologin', '--user-group', $user,
            ], null, false, null, true);
        } else {
            $ctx->out("System user {$user} already existed (orphan from a failed attempt) — reusing it.");
        }

        if (!$ctx->dryRun) {
            foreach (["{$docRoot}", "{$home}/logs", "{$home}/tmp"] as $dir) {
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
            }
        }

        // PHP-FPM pool
        self::writePool($ctx, $domain, $php, []);

        // Apache vhost (www is the default alias)
        self::writeVhost($ctx, $domain, $docRoot, $socket, $home, ["www.{$domain}"]);

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

        // Chroot-safe ownership: the site dir is root-owned (required for
        // SFTP ChrootDirectory); the writable subdirs belong to the site user.
        // htdocs is setgid + group-writable so SFTP accounts in the site group
        // can upload and new files inherit the group.
        $ctx->run(['chown', 'root:root', $home]);
        $ctx->run(['chmod', '0755', $home]);
        $ctx->run(['chown', '-R', "{$user}:{$user}", $docRoot, "{$home}/logs", "{$home}/tmp"]);
        $ctx->run(['chmod', '2775', $docRoot]);

        $ctx->run(['a2ensite', '--quiet', "{$domain}.conf"]);
        $ctx->run(['systemctl', 'restart', "php{$php}-fpm"]);
        $ctx->run(['systemctl', 'reload', 'apache2']);

        $ctx->out("Site {$domain} created: docroot {$docRoot}, PHP {$php} pool as user {$user}.");
        return 0;
    }

    public static function userExists(string $user): bool
    {
        return function_exists('posix_getpwnam') && posix_getpwnam($user) !== false;
    }

    /**
     * (Re)write a site's Apache vhost with the given alias domains.
     *
     * @param list<string> $aliases extra ServerAlias hostnames
     */
    public static function writeVhost(
        Ctx $ctx,
        string $domain,
        string $docRoot,
        string $socket,
        string $home,
        array $aliases
    ): void {
        $aliasLines = '';
        foreach ($aliases as $alias) {
            $aliasLines .= "    ServerAlias " . Validate::domain($alias) . "\n";
        }

        $ctx->writeFile(
            "/etc/apache2/sites-available/{$domain}.conf",
            $ctx->template('vhost.conf.tpl', [
                'domain' => $domain,
                'doc_root' => $docRoot,
                'socket' => $socket,
                'home' => $home,
                'server_aliases' => rtrim($aliasLines, "\n"),
            ])
        );
    }

    /**
     * Replace a site's alias domains (JSON list of hostnames on stdin) and
     * reload Apache. www.<domain> is always kept.
     *
     * @param array<string, string> $flags
     */
    public static function aliases(Ctx $ctx, array $flags): int
    {
        $domain = Validate::domain($flags['domain'] ?? '');
        $home = "/var/www/{$domain}";
        $docRoot = "{$home}/htdocs";
        $socket = "/run/php/{$domain}.sock";

        $raw = $ctx->dryRun ? '[]' : $ctx->stdin();
        $list = json_decode($raw === '' ? '[]' : $raw, true);
        if (!is_array($list)) {
            throw new InvalidArgumentException('Expected a JSON array of alias domains on stdin.');
        }

        $aliases = ["www.{$domain}"];
        foreach ($list as $alias) {
            $alias = Validate::domain((string) $alias);
            if ($alias !== $domain && !in_array($alias, $aliases, true)) {
                $aliases[] = $alias;
            }
        }

        self::writeVhost($ctx, $domain, $docRoot, $socket, $home, $aliases);
        $ctx->run(['systemctl', 'reload', 'apache2']);
        $ctx->out(count($aliases) . " alias domain(s) set for {$domain}: " . implode(', ', $aliases));
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

        $ctx->deletePath("/etc/hostingpanel/site-access/{$domain}.conf");
        $ctx->deletePath("/etc/hostingpanel/site-access/{$domain}.ipmode.conf");
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
