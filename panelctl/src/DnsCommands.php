<?php

declare(strict_types=1);

/**
 * Authoritative DNS via PowerDNS (gmysql backend, database "powerdns").
 *
 * The panel is the source of truth and sends the full record set for a
 * zone (JSON on stdin); panelctl manages the SOA + NS + nameserver glue
 * itself and bumps the serial, then inserts the user records. Every value
 * is validated per record type and SQL-escaped — this runs as root.
 */
final class DnsCommands
{
    private const DB = 'powerdns';
    private const TYPES = ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SRV', 'CAA'];

    /** Full resync of one zone. @param array<string, string> $flags */
    public static function sync(Ctx $ctx, array $flags): int
    {
        $zone = Validate::domain($flags['domain'] ?? '');
        $raw = $ctx->dryRun ? '[]' : $ctx->stdin();
        $records = json_decode($raw === '' ? '[]' : $raw, true);
        if (!is_array($records)) {
            throw new InvalidArgumentException('Expected a JSON array of records on stdin.');
        }

        $ns1 = "ns1.{$zone}";
        $ns2 = "ns2.{$zone}";
        $serial = $ctx->dryRun ? 1 : time();
        $ip4 = Net::ipv4();
        $ip6 = Net::ipv6();

        // Create the zone, then rebuild all of its records atomically.
        $ctx->mysql(self::DB, "INSERT IGNORE INTO domains (name, type) VALUES ('{$zone}', 'NATIVE')");
        $did = "(SELECT id FROM domains WHERE name='{$zone}')";
        $ctx->mysql(self::DB, "DELETE FROM records WHERE domain_id={$did}");

        $rows = [];
        $rows[] = self::row($zone, 'SOA',
            "{$ns1} hostmaster.{$zone} {$serial} 10800 3600 604800 3600", 3600, 0);
        $rows[] = self::row($zone, 'NS', $ns1, 3600, 0);
        $rows[] = self::row($zone, 'NS', $ns2, 3600, 0);
        if ($ip4 !== null) {
            $rows[] = self::row($ns1, 'A', $ip4, 3600, 0);
            $rows[] = self::row($ns2, 'A', $ip4, 3600, 0);
        }
        if ($ip6 !== null) {
            $rows[] = self::row($ns1, 'AAAA', $ip6, 3600, 0);
            $rows[] = self::row($ns2, 'AAAA', $ip6, 3600, 0);
        }

        foreach ($records as $rec) {
            [$name, $type, $content, $ttl, $prio] = self::validateRecord($zone, $rec);
            $rows[] = self::row($name, $type, $content, $ttl, $prio);
        }

        $values = implode(",\n", array_map(
            fn ($r) => "({$did}, '{$r[0]}', '{$r[1]}', '{$r[2]}', {$r[3]}, {$r[4]})",
            $rows
        ));
        $ctx->mysql(self::DB,
            "INSERT INTO records (domain_id, name, type, content, ttl, prio) VALUES {$values}");
        $ctx->run(['pdns_control', 'reload'], null, true);

        $ctx->out("Zone {$zone} synced (" . count($rows) . " records). "
            . "Set your registrar's nameservers to {$ns1} and {$ns2}"
            . ($ip4 !== null ? " (glue {$ns1}/{$ns2} -> {$ip4})." : '.'));
        return 0;
    }

    /** @param array<string, string> $flags */
    public static function zoneDelete(Ctx $ctx, array $flags): int
    {
        $zone = Validate::domain($flags['domain'] ?? '');
        // records cascade is not guaranteed; delete both explicitly.
        $ctx->mysql(self::DB,
            "DELETE FROM records WHERE domain_id=(SELECT id FROM domains WHERE name='{$zone}')");
        $ctx->mysql(self::DB, "DELETE FROM domains WHERE name='{$zone}'");
        $ctx->run(['pdns_control', 'reload'], null, true);
        $ctx->out("DNS zone {$zone} removed.");
        return 0;
    }

    /**
     * Add/remove the transient _acme-challenge TXT used by DNS-01 wildcard
     * issuance. Called by certbot's manual hooks. @param array<string,string> $flags
     */
    public static function acme(Ctx $ctx, array $flags): int
    {
        $zone = Validate::domain($flags['domain'] ?? '');
        $action = $flags['action'] ?? '';
        $name = "_acme-challenge.{$zone}";
        $did = "(SELECT id FROM domains WHERE name='{$zone}')";

        if ($action === 'add') {
            $value = $flags['value'] ?? '';
            if (!preg_match('/^[A-Za-z0-9_-]{20,128}$/', $value)) {
                throw new InvalidArgumentException('Invalid ACME validation value.');
            }
            $ctx->mysql(self::DB,
                "INSERT INTO records (domain_id, name, type, content, ttl) "
                . "VALUES ({$did}, '{$name}', 'TXT', '\"{$value}\"', 60)");
            $ctx->out("ACME TXT added for {$zone}.");
        } elseif ($action === 'del') {
            $ctx->mysql(self::DB,
                "DELETE FROM records WHERE domain_id={$did} AND type='TXT' AND name='{$name}'");
            $ctx->out("ACME TXT removed for {$zone}.");
        } else {
            throw new InvalidArgumentException('action must be add or del.');
        }
        $ctx->run(['pdns_control', 'reload'], null, true);
        return 0;
    }

    /**
     * @param array<string, mixed> $rec
     * @return array{0:string,1:string,2:string,3:int,4:int} [name,type,content,ttl,prio]
     */
    private static function validateRecord(string $zone, array $rec): array
    {
        $type = strtoupper(trim((string) ($rec['type'] ?? '')));
        if (!in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException("Unsupported DNS record type: {$type}");
        }

        $rawName = trim((string) ($rec['name'] ?? '@'));
        if ($rawName === '@' || $rawName === '') {
            $fqdn = $zone;
        } else {
            if (!preg_match('/^(\*\.)?([a-z0-9_]([a-z0-9_-]{0,61}[a-z0-9])?\.)*[a-z0-9_]([a-z0-9_-]{0,61}[a-z0-9])?$/i', $rawName)) {
                throw new InvalidArgumentException("Invalid record name: {$rawName}");
            }
            $fqdn = strtolower($rawName) . '.' . $zone;
        }

        $content = trim((string) ($rec['content'] ?? ''));
        $content = match ($type) {
            'A' => self::ip($content, FILTER_FLAG_IPV4, 'IPv4'),
            'AAAA' => self::ip($content, FILTER_FLAG_IPV6, 'IPv6'),
            'CNAME', 'NS' => self::hostname($content),
            'MX' => self::hostname($content),
            'TXT' => '"' . self::txt($content) . '"',
            'SRV', 'CAA' => self::printable($content),
        };

        $ttl = (int) ($rec['ttl'] ?? 3600);
        $ttl = $ttl >= 60 && $ttl <= 604800 ? $ttl : 3600;
        $prio = $type === 'MX' || $type === 'SRV' ? max(0, (int) ($rec['prio'] ?? 10)) : 0;

        return [self::esc($fqdn), $type, self::esc($content), $ttl, $prio];
    }

    private static function ip(string $v, int $flag, string $label): string
    {
        if (filter_var($v, FILTER_VALIDATE_IP, $flag) === false) {
            throw new InvalidArgumentException("Invalid {$label} address: {$v}");
        }
        return $v;
    }

    private static function hostname(string $v): string
    {
        // PowerDNS gmysql stores content hostnames WITHOUT a trailing dot.
        $v = rtrim(strtolower($v), '.');
        if (!preg_match('/^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $v)) {
            throw new InvalidArgumentException("Invalid hostname: {$v}");
        }
        return $v;
    }

    private static function txt(string $v): string
    {
        if (strlen($v) > 512 || preg_match('/[\x00-\x1f"\\\\]/', $v)) {
            throw new InvalidArgumentException('TXT value has illegal characters or is too long.');
        }
        return $v;
    }

    private static function printable(string $v): string
    {
        if (strlen($v) > 255 || preg_match('/[\x00-\x1f]/', $v)) {
            throw new InvalidArgumentException('Record value has illegal characters.');
        }
        return $v;
    }

    /** @return array{0:string,1:string,2:string,3:int,4:int} */
    private static function row(string $name, string $type, string $content, int $ttl, int $prio): array
    {
        return [self::esc(strtolower($name)), $type, self::esc($content), $ttl, $prio];
    }

    private static function esc(string $s): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $s);
    }
}
