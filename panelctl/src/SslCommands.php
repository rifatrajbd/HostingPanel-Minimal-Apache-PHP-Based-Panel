<?php

declare(strict_types=1);

final class SslCommands
{
    /** @param array<string, string> $flags */
    public static function issue(Ctx $ctx, array $flags): int
    {
        $domain = Validate::domain($flags['domain'] ?? '');
        $includeWww = ($flags['www'] ?? '') === '1';

        $argv = [
            'certbot', '--apache', '--non-interactive', '--agree-tos',
            '--redirect', '-d', $domain,
        ];
        if ($includeWww) {
            $argv[] = '-d';
            $argv[] = "www.{$domain}";
        }
        self::emailArgs($argv);

        $ctx->run($argv);
        $ctx->out("Certificate issued for {$domain}" . ($includeWww ? " and www.{$domain}" : '') . '.');
        return 0;
    }

    /** All certificates as JSON: name, domains, expiry, valid days. */
    public static function list(Ctx $ctx, array $flags): int
    {
        if ($ctx->dryRun) {
            $ctx->out('[]');
            return 0;
        }
        $out = $ctx->run(['certbot', 'certificates'], null, true);
        $certs = [];
        $current = null;
        foreach (explode("\n", $out) as $line) {
            $line = trim($line);
            if (preg_match('/^Certificate Name:\s*(.+)$/', $line, $m)) {
                if ($current !== null) {
                    $certs[] = $current;
                }
                $current = ['name' => $m[1], 'domains' => '', 'expiry' => '', 'status' => ''];
            } elseif ($current !== null && preg_match('/^Domains:\s*(.+)$/', $line, $m)) {
                $current['domains'] = $m[1];
            } elseif ($current !== null && preg_match('/^Expiry Date:\s*([0-9-]+ [0-9:]+)[^(]*\(([^)]*)\)/', $line, $m)) {
                $current['expiry'] = $m[1];
                $current['status'] = $m[2];
            }
        }
        if ($current !== null) {
            $certs[] = $current;
        }
        $ctx->out((string) json_encode($certs));
        return 0;
    }

    public static function renew(Ctx $ctx, array $flags): int
    {
        $domain = Validate::domain($flags['domain'] ?? '');
        $out = $ctx->run(['certbot', 'renew', '--cert-name', $domain, '--no-random-sleep-on-renew'], null, true);
        $ctx->out(trim($out) !== '' ? trim($out) : "Renewal attempted for {$domain}.");
        return 0;
    }

    public static function delete(Ctx $ctx, array $flags): int
    {
        $domain = Validate::domain($flags['domain'] ?? '');
        $ctx->run(['certbot', 'delete', '--non-interactive', '--cert-name', $domain]);
        $ctx->out("Certificate {$domain} deleted.");
        return 0;
    }

    /** @param list<string> $argv */
    public static function emailArgs(array &$argv): void
    {
        $emailFile = '/etc/hostingpanel/le-email';
        $email = is_file($emailFile) ? trim((string) file_get_contents($emailFile)) : '';
        if ($email !== '') {
            array_push($argv, '-m', $email);
        } else {
            $argv[] = '--register-unsafely-without-email';
        }
    }
}
