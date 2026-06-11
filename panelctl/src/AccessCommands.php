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
            $body = $ctx->run(['curl', '-fsSL', '--max-time', '20', $url]);
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
