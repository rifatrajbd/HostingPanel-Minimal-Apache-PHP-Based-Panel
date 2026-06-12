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
        return self::primary('udp://[2606:4700:4700::1111]:53');
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
