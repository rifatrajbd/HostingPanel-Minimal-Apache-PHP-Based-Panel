<?php

declare(strict_types=1);

/**
 * Server address discovery. Uses a UDP "connect" (no packets are sent) so
 * the kernel resolves the source address from the routing table — returns
 * null for an address family that has no route configured.
 */
final class Net
{
    public static function ipv4(): ?string
    {
        return self::primary('udp://1.1.1.1:53');
    }

    public static function ipv6(): ?string
    {
        // Prefer the address configured on an interface; fall back to the
        // route trick. (A server can have a global IPv6 with no default route.)
        return self::interfaceIpv6() ?? self::primary('udp://[2606:4700:4700::1111]:53');
    }

    /** First global-scope IPv6 from the kernel (no routing required). */
    private static function interfaceIpv6(): ?string
    {
        if (!is_readable('/proc/net/if_inet6')) {
            return null;
        }
        foreach (file('/proc/net/if_inet6', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $f = preg_split('/\s+/', trim($line));
            if (count($f) < 6 || $f[3] !== '00' || $f[5] === 'lo') {
                continue; // scope 00 = global; skip loopback
            }
            $addr = @inet_ntop(@hex2bin($f[0]) ?: '');
            if ($addr === false || str_starts_with($addr, 'fe80') || str_starts_with($addr, 'fc')
                || str_starts_with($addr, 'fd') || $addr === '::1') {
                continue;
            }
            return $addr;
        }
        return null;
    }

    private static function primary(string $target): ?string
    {
        $sock = @stream_socket_client($target, $errno, $errstr, 1);
        if ($sock === false) {
            return null;
        }
        $name = @stream_socket_get_name($sock, false);
        fclose($sock);
        if (!is_string($name) || $name === '') {
            return null;
        }
        if ($name[0] === '[') {
            return substr($name, 1, (int) strpos($name, ']') - 1);
        }
        return substr($name, 0, (int) strrpos($name, ':'));
    }
}
