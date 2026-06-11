<?php

declare(strict_types=1);

namespace Panel\Services;

final class CtlResult
{
    public function __construct(
        public readonly int $code,
        public readonly string $stdout,
        public readonly string $stderr
    ) {
    }

    public function ok(): bool
    {
        return $this->code === 0;
    }

    public function output(): string
    {
        return trim($this->stdout . ($this->stderr !== '' ? "\n" . $this->stderr : ''));
    }
}

/**
 * Bridge to the privileged panelctl CLI.
 *
 * Production: sudo -n /usr/local/bin/panelctl <command> [--flag value …]
 * Dev:        php panelctl/panelctl <command> --dry-run [--flag value …]
 *
 * Secrets are always passed via stdin (never argv/env, which are visible
 * in the process list).
 */
final class PanelCtl
{
    private readonly bool $dev;

    /** @param array<string, mixed> $settings */
    public function __construct(private readonly array $settings)
    {
        $this->dev = $settings['env'] === 'dev';
    }

    /** @param array<string, string> $flags */
    public function run(string $command, array $flags = [], ?string $stdin = null): CtlResult
    {
        if ($this->dev) {
            $argv = [PHP_BINARY, $this->settings['panelctl_dev_script'], $command, '--dry-run'];
        } else {
            $argv = ['sudo', '-n', $this->settings['panelctl_bin'], $command];
        }
        foreach ($flags as $name => $value) {
            $argv[] = '--' . $name;
            $argv[] = $value;
        }

        $proc = proc_open($argv, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        if (!is_resource($proc)) {
            return new CtlResult(127, '', 'Failed to start panelctl');
        }

        if ($stdin !== null) {
            fwrite($pipes[0], $stdin);
        }
        fclose($pipes[0]);

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);

        return new CtlResult($code, $stdout, $stderr);
    }
}
