<?php

declare(strict_types=1);

/**
 * Execution context for panelctl commands: shell execution, file writes
 * and template rendering — all dry-run aware.
 */
final class Ctx
{
    public function __construct(
        public readonly bool $dryRun,
        public readonly string $templateDir
    ) {
    }

    /**
     * Run an external command (argv array — no shell involved).
     *
     * @param list<string> $argv
     */
    public function run(array $argv, ?string $stdin = null, bool $allowFail = false, ?string $cwd = null): string
    {
        if ($this->dryRun) {
            $this->out('[dry-run] exec: ' . implode(' ', $argv));
            return '';
        }

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
        $code = proc_close($proc);

        if ($code !== 0 && !$allowFail) {
            throw new RuntimeException(
                implode(' ', array_slice($argv, 0, 2)) . " failed ({$code}): " . trim($stderr ?: $stdout)
            );
        }
        return $stdout;
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

    /** Read a secret from stdin (single line). */
    public function readSecret(): string
    {
        if ($this->dryRun) {
            // Consume stdin if present but never echo it.
            if (!stream_isatty(STDIN)) {
                stream_get_contents(STDIN);
            }
            return 'dry-run-secret';
        }
        $line = fgets(STDIN);
        if ($line === false || trim($line) === '') {
            throw new RuntimeException('Expected secret on stdin.');
        }
        return trim($line);
    }

    public function mysql(string $database, string $sql): string
    {
        return $this->run(['mysql', '--batch', '--skip-column-names', $database, '-e', $sql]);
    }

    public function out(string $message): void
    {
        fwrite(STDOUT, $message . "\n");
    }
}
