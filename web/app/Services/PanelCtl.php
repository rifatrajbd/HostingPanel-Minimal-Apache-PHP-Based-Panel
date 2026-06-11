<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class CtlResult
{
    public function __construct(
        public readonly int $code,
        public readonly string $stdout,
        public readonly string $stderr,
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
 * Bridge to the privileged panelctl CLI (same binary the Slim panel used).
 *
 * Production: sudo -n /usr/local/bin/panelctl <command> [--flag value …]
 * Dev:        php panelctl/panelctl <command> --dry-run [--flag value …]
 *
 * Secrets are always passed via stdin (never argv/env).
 */
class PanelCtl
{
    private bool $dev;

    public function __construct()
    {
        $this->dev = config('hostingpanel.dev', false);
    }

    /** @param array<string, string> $flags */
    public function run(string $command, array $flags = [], ?string $stdin = null): CtlResult
    {
        if ($this->dev) {
            $argv = [PHP_BINARY, config('hostingpanel.panelctl_dev'), $command, '--dry-run'];
        } else {
            $argv = ['sudo', '-n', config('hostingpanel.panelctl_bin'), $command];
        }
        foreach ($flags as $name => $value) {
            $argv[] = '--' . $name;
            $argv[] = (string) $value;
        }

        $process = new Process($argv, null, null, $stdin, 300);
        $process->run();

        return new CtlResult(
            $process->getExitCode() ?? 1,
            $process->getOutput(),
            $process->getErrorOutput(),
        );
    }
}
