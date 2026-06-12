<?php

declare(strict_types=1);

/**
 * Cloudflare-only access: when enabled for a site, only Cloudflare's
 * published IP ranges may reach it (direct-to-origin requests are
 * rejected). mod_remoteip restores the real visitor IP from
 * CF-Connecting-IP so logs and fail2ban keep working.
 */
final class AccessCommands
{
    private const REQUIRE_FILE = '/etc/hostingpanel/cloudflare-require.conf';
    private const REMOTEIP_FILE = '/etc/apache2/conf-available/hostingpanel-remoteip.conf';
    private const ACCESS_DIR = '/etc/hostingpanel/site-access';

    /** Refresh Cloudflare IP ranges (run at install + weekly cron). */
    public static function cfUpdate(Ctx $ctx, array $flags): int
    {
        if ($ctx->dryRun) {
            $ctx->out('[dry-run] fetch Cloudflare IP ranges, write require + remoteip configs');
            return 0;
        }
        $ranges = [];
        foreach (['https://www.cloudflare.com/ips-v4', 'https://www.cloudflare.com/ips-v6'] as $url) {
            try {
                $body = $ctx->run(['curl', '-fsSL', '--connect-timeout', '15', '--max-time', '30', $url]);
            } catch (RuntimeException) {
                // Broken IPv6 routing on the host — retry over IPv4.
                $body = $ctx->run(['curl', '-fsSL', '-4', '--connect-timeout', '15', '--max-time', '30', $url]);
            }
            foreach (explode("\n", trim($body)) as $line) {
                $line = trim($line);
                if ($line !== '' && preg_match('#^[0-9a-fA-F:./]+$#', $line)) {
                    $ranges[] = $line;
                }
            }
        }
        if (count($ranges) < 10) {
            throw new RuntimeException('Cloudflare IP list looks wrong (' . count($ranges) . ' entries) — aborting.');
        }

        $require = "# Cloudflare IP ranges — auto-updated by panelctl cf:update\n";
        $remoteip = "RemoteIPHeader CF-Connecting-IP\n";
        foreach ($ranges as $range) {
            $require .= "Require ip {$range}\n";
            $remoteip .= "RemoteIPTrustedProxy {$range}\n";
        }
        $ctx->writeFile(self::REQUIRE_FILE, $require);
        $ctx->writeFile(self::REMOTEIP_FILE, $remoteip);
        $ctx->run(['a2enconf', '-q', 'hostingpanel-remoteip'], null, true);
        $ctx->run(['systemctl', 'reload', 'apache2'], null, true);
        $ctx->out('Cloudflare IP ranges updated (' . count($ranges) . ' ranges).');
        return 0;
    }

    /**
     * Restrict which IP family a site is served over.
     *   both  — respond on IPv4 and IPv6 (default; removes the restriction)
     *   ipv4  — IPv4 only  (deny IPv6 clients)
     *   ipv6  — IPv6 only  (deny IPv4 clients)
     * "both off" is impossible: the only valid modes are the three above.
     *
     * Written as a per-site include so it applies to both the :80 and the
     * certbot-generated :443 vhost.
     */
    public static function siteIpMode(Ctx $ctx, array $flags): int
    {
        $domain = Validate::domain($flags['domain'] ?? '');
        $mode = $flags['mode'] ?? 'both';
        if (!in_array($mode, ['both', 'ipv4', 'ipv6'], true)) {
            throw new InvalidArgumentException('IP mode must be both, ipv4 or ipv6.');
        }
        $file = self::ACCESS_DIR . "/{$domain}.ipmode.conf";

        if ($mode === 'both') {
            $ctx->deletePath($file);
            $ctx->out("Site {$domain} is served over both IPv4 and IPv6.");
        } else {
            // IPv6 client addresses contain a colon; IPv4 ones do not.
            $condition = $mode === 'ipv4' ? '%{REMOTE_ADDR} =~ /:/' : '! (%{REMOTE_ADDR} =~ /:/)';
            $denied = $mode === 'ipv4' ? 'IPv6' : 'IPv4';
            $ctx->writeFile(
                $file,
                "# {$denied} disabled for this site by HostingPanel\n"
                . "<If \"{$condition}\">\n    Require all denied\n</If>\n"
            );
            $ctx->out("Site {$domain} is now " . strtoupper($mode) . "-only ({$denied} clients are refused).");
        }
        $ctx->run(['systemctl', 'reload', 'apache2'], null, true);
        return 0;
    }

    /** Toggle Cloudflare-only access for one site. */
    public static function siteCfOnly(Ctx $ctx, array $flags): int
    {
        $domain = Validate::domain($flags['domain'] ?? '');
        $enable = ($flags['enable'] ?? '') === '1';
        $file = self::ACCESS_DIR . "/{$domain}.conf";

        if ($enable) {
            if (!$ctx->dryRun && !is_file(self::REQUIRE_FILE)) {
                self::cfUpdate($ctx, []);
            }
            $ctx->writeFile(
                $file,
                "<Location \"/\">\n    Include " . self::REQUIRE_FILE . "\n</Location>\n"
            );
            $ctx->out("Cloudflare-only access ENABLED for {$domain}. Make sure the domain is proxied (orange cloud) in Cloudflare!");
        } else {
            $ctx->deletePath($file);
            $ctx->out("Cloudflare-only access disabled for {$domain}.");
        }
        $ctx->run(['systemctl', 'reload', 'apache2'], null, true);
        return 0;
    }
}
