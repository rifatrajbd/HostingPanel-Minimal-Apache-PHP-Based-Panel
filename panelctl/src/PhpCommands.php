<?php

declare(strict_types=1);

/**
 * Per-site PHP version switching and php.ini overrides, plus the
 * server-wide extension manager (phpenmod/phpdismod/apt).
 */
final class PhpCommands
{
    public const INI_DEFAULTS = [
        'memory_limit' => '256M',
        'upload_max_filesize' => '64M',
        'post_max_size' => '64M',
        'max_execution_time' => '120',
        'display_errors' => 'off',
    ];

    private const INI_RULES = [
        'memory_limit' => '/^\d{1,5}M$/',
        'upload_max_filesize' => '/^\d{1,5}M$/',
        'post_max_size' => '/^\d{1,5}M$/',
        'max_execution_time' => '/^\d{1,4}$/',
        'display_errors' => '/^(on|off)$/',
    ];

    /** Re-render a site's FPM pool with ini overrides (JSON on stdin). */
    public static function siteIni(Ctx $ctx, array $flags): int
    {
        $domain = Validate::domain($flags['domain'] ?? '');
        $php = Validate::phpVersion($flags['php'] ?? '');
        $ini = self::readIni($ctx);

        SiteCommands::writePool($ctx, $domain, $php, $ini);
        $ctx->run(['systemctl', 'restart', "php{$php}-fpm"]);
        $ctx->out("PHP settings updated for {$domain}.");
        return 0;
    }

    /** Switch a site to another PHP version (ini overrides JSON on stdin). */
    public static function sitePhp(Ctx $ctx, array $flags): int
    {
        $domain = Validate::domain($flags['domain'] ?? '');
        $old = Validate::phpVersion($flags['old'] ?? '');
        $new = Validate::phpVersion($flags['new'] ?? '');
        $ini = self::readIni($ctx);

        SiteCommands::writePool($ctx, $domain, $new, $ini);
        $ctx->deletePath("/etc/php/{$old}/fpm/pool.d/{$domain}.conf");
        $ctx->run(['systemctl', 'restart', "php{$new}-fpm"]);
        $ctx->run(['systemctl', 'restart', "php{$old}-fpm"], null, true);
        $ctx->out("Site {$domain} switched from PHP {$old} to PHP {$new}.");
        return 0;
    }

    /** List loaded extensions for a PHP version (JSON). */
    public static function extList(Ctx $ctx, array $flags): int
    {
        $php = Validate::phpVersion($flags['php'] ?? '');
        if ($ctx->dryRun) {
            $ctx->out('[]');
            return 0;
        }
        $out = $ctx->run(["/usr/bin/php{$php}", '-m']);
        $exts = array_values(array_filter(
            array_map('trim', explode("\n", $out)),
            fn ($l) => $l !== '' && !str_starts_with($l, '[')
        ));
        $ctx->out((string) json_encode($exts));
        return 0;
    }

    /** Install / enable / disable an extension for a PHP version. */
    public static function ext(Ctx $ctx, array $flags): int
    {
        $php = Validate::phpVersion($flags['php'] ?? '');
        $name = $flags['name'] ?? '';
        $action = $flags['action'] ?? '';
        if (!preg_match('/^[a-z0-9_]{2,30}$/', $name)) {
            throw new InvalidArgumentException('Invalid extension name.');
        }

        switch ($action) {
            case 'install':
                $ctx->run(['apt-get', 'install', '-y', '-qq', "php{$php}-{$name}"]);
                break;
            case 'enable':
                $ctx->run(['phpenmod', '-v', $php, $name]);
                break;
            case 'disable':
                $ctx->run(['phpdismod', '-v', $php, $name]);
                break;
            default:
                throw new InvalidArgumentException('Action must be install, enable or disable.');
        }
        $ctx->run(['systemctl', 'restart', "php{$php}-fpm"]);
        $ctx->out("Extension {$name}: {$action} done for PHP {$php}.");
        return 0;
    }

    /** @return array<string, string> validated ini overrides merged over defaults */
    public static function readIni(Ctx $ctx): array
    {
        $raw = $ctx->dryRun ? '{}' : $ctx->stdin();
        $data = json_decode($raw === '' ? '{}' : $raw, true);
        if (!is_array($data)) {
            $data = [];
        }
        $ini = self::INI_DEFAULTS;
        foreach ($data as $key => $value) {
            if (!isset(self::INI_RULES[$key])) {
                continue;
            }
            $value = strtolower(trim((string) $value));
            if (!preg_match(self::INI_RULES[$key], $value)) {
                throw new InvalidArgumentException("Invalid value for {$key}: {$value}");
            }
            $ini[$key] = $value;
        }
        return $ini;
    }
}
