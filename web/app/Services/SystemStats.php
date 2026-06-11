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
        return [
            'load' => $this->load(),
            'cpu_count' => $this->cpuCount(),
            'memory' => $this->memory(),
            'disk' => $this->disk(),
            'uptime' => $this->uptime(),
            'hostname' => php_uname('n'),
            'os' => PHP_OS_FAMILY === 'Linux'
                ? trim((string) @file_get_contents('/etc/issue.net')) : php_uname('s'),
        ];
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
