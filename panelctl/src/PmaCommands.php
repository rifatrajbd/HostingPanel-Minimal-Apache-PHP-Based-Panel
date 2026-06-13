<?php

declare(strict_types=1);

/**
 * phpMyAdmin single sign-on. Creates a dedicated MySQL user, stores its
 * password where the panel can read it, and switches phpMyAdmin to "signon"
 * auth so a logged-in panel admin is auto-authenticated (no second login).
 *
 * The signon session is only ever created by the panel's auth-protected
 * /phpmyadmin-sso route, so phpMyAdmin stays locked to panel admins.
 */
final class PmaCommands
{
    private const PW_FILE = '/etc/hostingpanel/pma-password';
    private const PMA_DIR = '/opt/hostingpanel/phpmyadmin';
    private const PMA_CONFIG = self::PMA_DIR . '/config.inc.php';

    /** @param array<string, string> $flags */
    public static function setup(Ctx $ctx, array $flags): int
    {
        if ($ctx->dryRun) {
            $ctx->out('[dry-run] configure phpMyAdmin single sign-on');
            return 0;
        }

        // Reuse an existing password so repeated runs (self-update) are stable.
        $password = is_file(self::PW_FILE)
            ? trim((string) file_get_contents(self::PW_FILE))
            : bin2hex(random_bytes(16));

        $ctx->mysql('mysql', "CREATE USER IF NOT EXISTS 'pma'@'127.0.0.1' IDENTIFIED BY '{$password}'");
        $ctx->mysql('mysql', "ALTER USER 'pma'@'127.0.0.1' IDENTIFIED BY '{$password}'");
        $ctx->mysql('mysql', "GRANT ALL PRIVILEGES ON *.* TO 'pma'@'127.0.0.1' WITH GRANT OPTION");
        $ctx->mysql('mysql', 'FLUSH PRIVILEGES');

        // Readable by the unprivileged panel user (which puts it in the session).
        $ctx->writeFile(self::PW_FILE, $password . "\n", 0640);
        @chgrp(self::PW_FILE, 'hostingpanel');

        if (is_dir(self::PMA_DIR)) {
            $blowfish = self::blowfishSecret();
            $config = "<?php\n"
                . "\$cfg['blowfish_secret'] = '{$blowfish}';\n"
                . "\$cfg['TempDir'] = '/var/lib/hostingpanel/pma-tmp';\n"
                . "\$i = 1;\n"
                . "\$cfg['Servers'][\$i]['auth_type'] = 'signon';\n"
                . "\$cfg['Servers'][\$i]['SignonSession'] = 'HostingPanelPMA';\n"
                . "\$cfg['Servers'][\$i]['SignonURL'] = '/phpmyadmin-sso';\n"
                . "\$cfg['Servers'][\$i]['host'] = '127.0.0.1';\n"
                . "\$cfg['Servers'][\$i]['AllowNoPassword'] = false;\n";
            $ctx->writeFile(self::PMA_CONFIG, $config, 0644);
        }

        $ctx->out('phpMyAdmin single sign-on configured.');
        return 0;
    }

    /** Keep the existing blowfish secret if one is set, else generate one. */
    private static function blowfishSecret(): string
    {
        if (is_file(self::PMA_CONFIG)) {
            $current = (string) file_get_contents(self::PMA_CONFIG);
            if (preg_match("/blowfish_secret'\\]\\s*=\\s*'([^']+)'/", $current, $m) && strlen($m[1]) >= 32) {
                return $m[1];
            }
        }
        return substr(base64_encode(random_bytes(24)), 0, 32);
    }
}
