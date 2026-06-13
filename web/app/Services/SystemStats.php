<?php

namespace App\Services;

/**
 * Reads system metrics directly (no privileges). Degrades gracefully
 * on non-Linux dev machines.
 */
class SystemStats
{
    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        $addresses = $this->addresses();

        return [
            'load' => $this->load(),
            'cpu_count' => $this->cpuCount(),
            'memory' => $this->memory(),
            'disk' => $this->disk(),
            'uptime' => $this->uptime(),
            'hostname' => php_uname('n'),
            'ipv4' => $addresses['ipv4'],
            'ipv6' => $addresses['ipv6'],
            'os' => PHP_OS_FAMILY === 'Linux'
                ? trim((string) @file_get_contents('/etc/issue.net')) : php_uname('s'),
        ];
    }

    /**
     * Primary outbound IPv4 / IPv6 of this server. Uses a UDP "connect"
     * (no packets are sent) so the kernel picks the source address from
     * the routing table — returns null for a family with no route.
     *
     * @return array{ipv4: ?string, ipv6: ?string}
     */
    public function addresses(): array
    {
        return [
            'ipv4' => $this->primaryAddress('udp://1.1.1.1:53'),
            // Prefer the address actually configured on an interface — the
            // route trick returns null when there's an IPv6 address but no
            // usable default IPv6 route.
            'ipv6' => $this->interfaceIpv6() ?? $this->primaryAddress('udp://[2606:4700:4700::1111]:53'),
        ];
    }

    /** First global-scope IPv6 from the kernel (no routing required). */
    private function interfaceIpv6(): ?string
    {
        if (!is_readable('/proc/net/if_inet6')) {
            return null;
        }
        foreach (file('/proc/net/if_inet6', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $f = preg_split('/\s+/', trim($line));
            // columns: hexaddr ifindex prefixlen scope flags ifname
            if (count($f) < 6 || $f[3] !== '00' || $f[5] === 'lo') {
                continue; // scope 00 = global; skip loopback
            }
            $addr = @inet_ntop(@hex2bin($f[0]) ?: '');
            if ($addr === false || str_starts_with($addr, 'fe80') || str_starts_with($addr, 'fc')
                || str_starts_with($addr, 'fd') || $addr === '::1') {
                continue; // skip link-local / unique-local
            }
            return $addr;
        }
        return null;
    }

    private function primaryAddress(string $target): ?string
    {
        $sock = @stream_socket_client($target, $errno, $errstr, 1);
        if ($sock === false) {
            return null;
        }
        $name = @stream_socket_get_name($sock, false); // "ip:port" or "[ip]:port"
        fclose($sock);
        if (!is_string($name) || $name === '') {
            return null;
        }
        // Strip the trailing :port (IPv6 is wrapped in [...]).
        if ($name[0] === '[') {
            return substr($name, 1, strpos($name, ']') - 1);
        }
        return substr($name, 0, strrpos($name, ':'));
    }

    private function load(): array
    {
        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : false;
        return $load === false
            ? [0.0, 0.0, 0.0]
            : [round($load[0], 2), round($load[1], 2), round($load[2], 2)];
    }

    private function cpuCount(): int
    {
        if (is_readable('/proc/cpuinfo')) {
            return max(1, (int) preg_match_all('/^processor/m', (string) file_get_contents('/proc/cpuinfo')));
        }
        return (int) (getenv('NUMBER_OF_PROCESSORS') ?: 1);
    }

    private function memory(): array
    {
        if (!is_readable('/proc/meminfo')) {
            return ['total_mb' => 0, 'used_mb' => 0, 'percent' => 0];
        }
        $info = (string) file_get_contents('/proc/meminfo');
        preg_match('/MemTotal:\s+(\d+)/', $info, $t);
        preg_match('/MemAvailable:\s+(\d+)/', $info, $a);
        $totalKb = (int) ($t[1] ?? 0);
        $usedKb = max(0, $totalKb - (int) ($a[1] ?? 0));
        return [
            'total_mb' => intdiv($totalKb, 1024),
            'used_mb' => intdiv($usedKb, 1024),
            'percent' => $totalKb > 0 ? (int) round($usedKb / $totalKb * 100) : 0,
        ];
    }

    private function disk(): array
    {
        $path = PHP_OS_FAMILY === 'Windows' ? 'C:' : '/';
        $total = (float) @disk_total_space($path);
        $free = (float) @disk_free_space($path);
        $used = max(0.0, $total - $free);
        return [
            'total_gb' => round($total / 1073741824, 1),
            'used_gb' => round($used / 1073741824, 1),
            'percent' => $total > 0 ? (int) round($used / $total * 100) : 0,
        ];
    }

    private function uptime(): string
    {
        if (!is_readable('/proc/uptime')) {
            return 'n/a';
        }
        $seconds = (int) (float) explode(' ', (string) file_get_contents('/proc/uptime'))[0];
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        return $days > 0 ? "{$days}d {$hours}h" : "{$hours}h {$minutes}m";
    }
}
