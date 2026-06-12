<?php

declare(strict_types=1);

/**
 * Execution context for panelctl commands: shell execution, file writes
 * and template rendering — all dry-run aware.
 */
final class Ctx
{
    /** Response text built up by command handlers via out(). */
    private string $output = '';

    /**
     * @param string $stdin Secret / payload that the caller sent in (instead
     *                      of the process STDIN), so the same command classes
     *                      work from both the CLI and the socket daemon.
     */
    public function __construct(
        public readonly bool $dryRun,
        public readonly string $templateDir,
        private readonly string $stdin = ''
    ) {
    }

    /**
     * Run an external command (argv array — no shell involved).
     *
     * When $retryOnLock is set, transient "cannot lock /etc/passwd" style
     * failures (a background apt job briefly holding the user/group lock)
     * are retried with backoff instead of failing the whole operation.
     *
     * @param list<string> $argv
     */
    public function run(
        array $argv,
        ?string $stdin = null,
        bool $allowFail = false,
        ?string $cwd = null,
        bool $retryOnLock = false
    ): string {
        if ($this->dryRun) {
            $this->out('[dry-run] exec: ' . implode(' ', $argv));
            return '';
        }

        $attempts = $retryOnLock ? 6 : 1;
        for ($attempt = 1; ; $attempt++) {
            [$code, $stdout, $stderr] = $this->exec($argv, $stdin, $cwd);

            if ($code === 0) {
                return $stdout;
            }

            $locked = stripos($stderr, 'cannot lock') !== false
                || stripos($stderr, 'try again later') !== false
                || stripos($stderr, 'resource temporarily unavailable') !== false;
            if ($locked && $attempt < $attempts) {
                sleep($attempt); // 1s, 2s, 3s… back off while the lock clears
                continue;
            }

            if ($allowFail) {
                return $stdout;
            }
            throw new RuntimeException(
                implode(' ', array_slice($argv, 0, 2)) . " failed ({$code}): " . trim($stderr ?: $stdout)
            );
        }
    }

    /**
     * @param list<string> $argv
     * @return array{0: int, 1: string, 2: string} [exit code, stdout, stderr]
     */
    private function exec(array $argv, ?string $stdin, ?string $cwd): array
    {
        $proc = proc_open($argv, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, $cwd);
        if (!is_resource($proc)) {
            throw new RuntimeException('Failed to start: ' . $argv[0]);
        }
        if ($stdin !== null) {
            fwrite($pipes[0], $stdin);
        }
        fclose($pipes[0]);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [proc_close($proc), $stdout, $stderr];
    }

    public function writeFile(string $path, string $content, int $mode = 0644): void
    {
        if ($this->dryRun) {
            $this->out("[dry-run] write {$path} (" . strlen($content) . ' bytes)');
            return;
        }
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException("Failed to write {$path}");
        }
        chmod($path, $mode);
    }

    public function deletePath(string $path): void
    {
        if ($this->dryRun) {
            $this->out("[dry-run] rm -rf {$path}");
            return;
        }
        if (is_file($path) || is_link($path)) {
            unlink($path);
        } elseif (is_dir($path)) {
            $this->run(['rm', '-rf', '--', $path]);
        }
    }

    /** @param array<string, string> $vars */
    public function template(string $name, array $vars): string
    {
        $content = file_get_contents($this->templateDir . '/' . $name);
        if ($content === false) {
            throw new RuntimeException("Template missing: {$name}");
        }
        foreach ($vars as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }
        return $content;
    }

    /** The full payload the caller sent in (JSON for some commands). */
    public function stdin(): string
    {
        return $this->dryRun ? '' : $this->stdin;
    }

    /** Read a single-line secret from the injected payload. */
    public function readSecret(): string
    {
        if ($this->dryRun) {
            // Alphanumeric + long enough to satisfy db/mailbox validators.
            return 'DryRunSecret1234';
        }
        $line = trim($this->stdin);
        if ($line === '') {
            throw new RuntimeException('Expected secret on stdin.');
        }
        // Only the first line is the secret.
        return trim(strtok($line, "\n"));
    }

    public function mysql(string $database, string $sql): string
    {
        return $this->run(['mysql', '--batch', '--skip-column-names', $database, '-e', $sql]);
    }

    /** Append a line to the response buffer (returned to the caller). */
    public function out(string $message): void
    {
        $this->output .= $message . "\n";
    }

    public function getOutput(): string
    {
        return $this->output;
    }
}
