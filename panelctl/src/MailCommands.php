<?php

declare(strict_types=1);

/**
 * Mail management. Postfix/Dovecot read virtual domains/users from the
 * MariaDB "mailserver" database (see etc/mail/ and the installer).
 * Mailbox passwords are bcrypt — Dovecot's BLF-CRYPT scheme.
 */
final class MailCommands
{
    private const DB = 'mailserver';
    private const DKIM_DIR = '/var/lib/rspamd/dkim';
    private const VMAIL_DIR = '/var/vmail';
    private const WEBMAIL_DOMAINS = '/var/lib/hostingpanel/webmail-data/_data_/_default_/domains';

    /** @param array<string, string> $flags */
    public static function domainAdd(Ctx $ctx, array $flags): int
    {
        $domain = Validate::domain($flags['domain'] ?? '');
        $selector = 'mail';

        $ctx->mysql(self::DB, "INSERT IGNORE INTO virtual_domains (name) VALUES ('{$domain}')");

        // DKIM key: written to file, DNS TXT record printed by rspamadm
        $keyFile = self::DKIM_DIR . "/{$domain}.{$selector}.key";
        $dnsRecord = $ctx->run([
            'rspamadm', 'dkim_keygen', '-d', $domain, '-s', $selector, '-b', '2048', '-k', $keyFile,
        ]);
        $ctx->run(['chown', '_rspamd:_rspamd', $keyFile], null, true);
        $ctx->run(['chmod', '0640', $keyFile], null, true);
        $ctx->run(['systemctl', 'reload', 'rspamd'], null, true);

        self::webmailDomainConfig($ctx, $domain);

        $ipv4 = Net::ipv4();
        $ipv6 = Net::ipv6();

        // Print the DNS records the user must create (panel stores this output).
        $ctx->out("# DNS records for {$domain} — create these at your DNS provider:");
        if ($ipv4 !== null) {
            $ctx->out("mail.{$domain}.  A     {$ipv4}");
        }
        if ($ipv6 !== null) {
            $ctx->out("mail.{$domain}.  AAAA  {$ipv6}");
        }
        $ctx->out("{$domain}.  MX 10  mail.{$domain}.");
        // SPF authorises both A and AAAA of the MX automatically via "mx".
        $ctx->out("{$domain}.  TXT  \"v=spf1 mx -all\"");
        $ctx->out(trim($dnsRecord));
        $ctx->out("_dmarc.{$domain}.  TXT  \"v=DMARC1; p=quarantine; rua=mailto:postmaster@{$domain}\"");
        $ctx->out('# Reverse DNS (PTR): ask your VPS provider to set the PTR of your');
        $ctx->out("# IPv4" . ($ipv6 !== null ? ' AND IPv6' : '') . " address to mail.{$domain} — required for good deliverability.");
        return 0;
    }

    /** @param array<string, string> $flags */
    public static function domainDelete(Ctx $ctx, array $flags): int
    {
        $domain = Validate::domain($flags['domain'] ?? '');

        // virtual_users/aliases cascade via foreign keys
        $ctx->mysql(self::DB, "DELETE FROM virtual_domains WHERE name = '{$domain}'");
        $ctx->deletePath(self::DKIM_DIR . "/{$domain}.mail.key");
        $ctx->deletePath(self::VMAIL_DIR . "/{$domain}");
        $ctx->deletePath(self::WEBMAIL_DOMAINS . "/{$domain}.ini");
        $ctx->run(['systemctl', 'reload', 'rspamd'], null, true);

        $ctx->out("Mail domain {$domain} removed (mailboxes, DKIM key, mail files).");
        return 0;
    }

    /** @param array<string, string> $flags */
    public static function mailboxAdd(Ctx $ctx, array $flags): int
    {
        $address = Validate::email($flags['address'] ?? '');
        $password = $ctx->readSecret();
        if (strlen($password) < 10 || strlen($password) > 128) {
            throw new InvalidArgumentException('Mailbox password must be 10-128 characters.');
        }
        [, $domain] = explode('@', $address, 2);

        // bcrypt produces only [./A-Za-z0-9$] — safe inside the SQL literal.
        $hash = '{BLF-CRYPT}' . password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        $ctx->mysql(self::DB, "INSERT INTO virtual_users (domain_id, email, password)
            SELECT id, '{$address}', '{$hash}' FROM virtual_domains WHERE name = '{$domain}'");

        if (!$ctx->dryRun) {
            $check = trim($ctx->mysql(
                self::DB,
                "SELECT COUNT(*) FROM virtual_users WHERE email = '{$address}'"
            ));
            if ($check === '0') {
                throw new RuntimeException("Mail domain {$domain} not found — add it first.");
            }
        }

        $ctx->out("Mailbox {$address} created. IMAP: mail.{$domain}:993 SSL · SMTP: mail.{$domain}:587 STARTTLS.");
        return 0;
    }

    /**
     * Pre-configure SnappyMail for this domain so webmail works without
     * touching its admin panel: IMAP via localhost:143 (local connections
     * are trusted by Dovecot) and SMTP via the 587 submission port.
     */
    private static function webmailDomainConfig(Ctx $ctx, string $domain): void
    {
        if (!$ctx->dryRun && !is_dir('/var/lib/hostingpanel/webmail-data')) {
            return; // webmail not installed — nothing to configure
        }
        $ini = <<<INI
            imap_host = "127.0.0.1"
            imap_port = 143
            imap_secure = "None"
            imap_short_login = Off
            sieve_use = Off
            smtp_host = "127.0.0.1"
            smtp_port = 587
            smtp_secure = "TLS"
            smtp_short_login = Off
            smtp_auth = On
            white_list = ""
            INI;
        $ctx->writeFile(self::WEBMAIL_DOMAINS . "/{$domain}.ini", $ini . "\n", 0640);
        $ctx->run(['chown', '-R', 'hostingpanel:hostingpanel', dirname(self::WEBMAIL_DOMAINS)], null, true);
    }

    /** Postfix queue as JSON lines (postqueue -j) — delivery states. */
    public static function queue(Ctx $ctx, array $flags): int
    {
        $ctx->out(trim($ctx->run(['postqueue', '-j'], null, true)));
        return 0;
    }

    public static function queueFlush(Ctx $ctx, array $flags): int
    {
        $ctx->run(['postqueue', '-f']);
        $ctx->out('Queue flush triggered — Postfix is retrying all deferred mail.');
        return 0;
    }

    public static function queueDelete(Ctx $ctx, array $flags): int
    {
        $id = strtoupper($flags['id'] ?? '');
        if (!preg_match('/^[A-F0-9]{6,20}$/', $id)) {
            throw new InvalidArgumentException('Invalid queue ID.');
        }
        $ctx->run(['postsuper', '-d', $id]);
        $ctx->out("Message {$id} deleted from the queue.");
        return 0;
    }

    /** Recent mail log (delivery results, bounces, rejections). */
    public static function log(Ctx $ctx, array $flags): int
    {
        if ($ctx->dryRun) {
            $ctx->out('[dry-run] mail log');
            return 0;
        }
        $file = is_file('/var/log/mail.log') ? '/var/log/mail.log' : '/var/log/maillog';
        $ctx->out(trim($ctx->run(['tail', '-n', '150', $file], null, true)));
        return 0;
    }

    /** @param array<string, string> $flags */
    public static function mailboxDelete(Ctx $ctx, array $flags): int
    {
        $address = Validate::email($flags['address'] ?? '');
        [$local, $domain] = explode('@', $address, 2);

        $ctx->mysql(self::DB, "DELETE FROM virtual_users WHERE email = '{$address}'");
        $ctx->deletePath(self::VMAIL_DIR . "/{$domain}/{$local}");

        $ctx->out("Mailbox {$address} deleted.");
        return 0;
    }
}
