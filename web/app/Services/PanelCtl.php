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
 * Bridge to the privileged backend.
 *
 * Production: connects to the root panelctld daemon over a private Unix
 *             socket and waits for the result — no sudo anywhere.
 * Dev:        runs the panelctl CLI with --dry-run (no system changes),
 *             so the panel can be developed on any machine.
 *
 * Secrets/payloads travel inside the request, never via argv/env.
 */
class PanelCtl
{
    private const MAX_FRAME = 67108864; // 64 MB, matches the daemon

    private bool $dev;

    public function __construct()
    {
        $this->dev = (bool) config('hostingpanel.dev', false);
    }

    /** @param array<string, string> $flags */
    public function run(string $command, array $flags = [], ?string $stdin = null): CtlResult
    {
        return $this->dev
            ? $this->runDevCli($command, $flags, $stdin)
            : $this->runOverSocket($command, $flags, $stdin ?? '');
    }

    /** @param array<string, string> $flags */
    private function runOverSocket(string $command, array $flags, string $stdin): CtlResult
    {
        $path = config('hostingpanel.socket');
        $conn = @stream_socket_client('unix://' . $path, $errno, $errstr, 5);
        if ($conn === false) {
            return new CtlResult(1, '', "Cannot reach panelctld at {$path}: {$errstr}");
        }

        stream_set_timeout($conn, 600);
        $request = json_encode([
            'command' => $command,
            'flags' => (object) $flags,
            'stdin' => $stdin,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        fwrite($conn, $request . "\n");
        $line = stream_get_line($conn, self::MAX_FRAME, "\n");
        fclose($conn);

        $response = json_decode((string) $line, true);
        if (!is_array($response)) {
            return new CtlResult(1, '', 'Invalid response from panelctld.');
        }

        return new CtlResult(
            (int) ($response['code'] ?? 1),
            (string) ($response['stdout'] ?? ''),
            (string) ($response['stderr'] ?? ''),
        );
    }

    /** @param array<string, string> $flags */
    private function runDevCli(string $command, array $flags, ?string $stdin): CtlResult
    {
        $argv = [PHP_BINARY, config('hostingpanel.panelctl_dev'), $command, '--dry-run'];
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
