<?php

declare(strict_types=1);

/**
 * File manager backend. Every path is confined to /var/www/<domain>;
 * '..' segments are rejected outright and symlink escapes are caught
 * with realpath containment checks. All mutations re-chown to the
 * site user so PHP-FPM keeps working.
 */
final class FsCommands
{
    private const UPLOAD_DIR = '/var/lib/hostingpanel/uploads';
    private const MAX_READ = 52428800; // 50 MB

    /** @param array<string, string> $flags */
    public static function list(Ctx $ctx, array $flags): int
    {
        [$base] = self::base($flags);
        if ($ctx->dryRun) {
            $ctx->out('[]');
            return 0;
        }
        $dir = self::resolve($base, $flags['path'] ?? '/', true);
        if (!is_dir($dir)) {
            throw new InvalidArgumentException('Not a directory.');
        }
        $withSizes = ($flags['sizes'] ?? '') === '1';
        $items = [];
        foreach (scandir($dir) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $full = $dir . '/' . $name;
            $isDir = is_dir($full) && !is_link($full);
            $size = is_file($full) ? (int) filesize($full) : 0;
            if ($isDir && $withSizes) {
                $size = self::dirSize($ctx, $full);
            }
            $items[] = [
                'name' => $name,
                'dir' => $isDir,
                'link' => is_link($full),
                'size' => $size,
                'mode' => substr(sprintf('%o', fileperms($full)), -3),
                'mtime' => filemtime($full),
                'owner' => self::ownerOf($full),
            ];
        }
        usort($items, fn ($a, $b) => [$b['dir'], strtolower($a['name'])] <=> [$a['dir'], strtolower($b['name'])]);
        $ctx->out((string) json_encode($items));
        return 0;
    }

    /** Recursive search by filename under the site, newest first (JSON list). */
    public static function search(Ctx $ctx, array $flags): int
    {
        [$base] = self::base($flags);
        if ($ctx->dryRun) {
            $ctx->out('[]');
            return 0;
        }
        $query = trim((string) ($flags['query'] ?? ''));
        if (strlen($query) < 2) {
            throw new InvalidArgumentException('Search term must be at least 2 characters.');
        }

        $out = $ctx->run([
            'find', $base, '-maxdepth', '8', '-iname', '*' . $query . '*',
            '-printf', "%y\t%s\t%T@\t%m\t%p\n",
        ], null, true);

        $items = [];
        foreach (explode("\n", trim($out)) as $line) {
            if ($line === '') {
                continue;
            }
            [$type, $size, $mtime, $mode, $full] = array_pad(explode("\t", $line, 5), 5, '');
            if ($full === $base) {
                continue;
            }
            $rel = substr($full, strlen($base));
            $items[] = [
                'name' => basename($full),
                'path' => $rel === '' ? '/' : $rel,
                'dir' => $type === 'd',
                'size' => (int) $size,
                'mode' => substr($mode, -3) ?: '644',
                'mtime' => (int) $mtime,
            ];
            if (count($items) >= 500) {
                break;
            }
        }
        $ctx->out((string) json_encode($items));
        return 0;
    }

    private static function dirSize(Ctx $ctx, string $path): int
    {
        $out = $ctx->run(['du', '-sb', '--', $path], null, true);
        return (int) strtok(trim($out), "\t");
    }

    private static function ownerOf(string $path): string
    {
        if (!function_exists('posix_getpwuid')) {
            return '';
        }
        $u = @posix_getpwuid((int) @fileowner($path));
        $g = @posix_getgrgid((int) @filegroup($path));
        return ($u['name'] ?? '?') . '/' . ($g['name'] ?? '?');
    }

    /** @param array<string, string> $flags */
    public static function read(Ctx $ctx, array $flags): int
    {
        [$base] = self::base($flags);
        $file = self::resolve($base, $flags['path'] ?? '', true);
        if ($ctx->dryRun) {
            $ctx->out('[dry-run] read ' . $file);
            return 0;
        }
        if (!is_file($file)) {
            throw new InvalidArgumentException('Not a file.');
        }
        if (filesize($file) > self::MAX_READ) {
            throw new RuntimeException('File larger than 50 MB — use SFTP for this one.');
        }
        $fh = fopen($file, 'rb');
        if ($fh === false) {
            throw new RuntimeException('Cannot open file.');
        }
        fpassthru($fh);
        fclose($fh);
        return 0;
    }

    /** Write file content from stdin (text editor save / new file). */
    public static function write(Ctx $ctx, array $flags): int
    {
        [$base, $user] = self::base($flags);
        $file = self::resolve($base, $flags['path'] ?? '');
        if ($ctx->dryRun) {
            $ctx->out('[dry-run] write ' . $file);
            return 0;
        }
        $content = $ctx->stdin();
        if (file_put_contents($file, $content) === false) {
            throw new RuntimeException('Write failed.');
        }
        self::own($ctx, $file, $user);
        $ctx->out('Saved ' . $flags['path']);
        return 0;
    }

    /** Move an uploaded temp file (staged by the panel) into the site. */
    public static function import(Ctx $ctx, array $flags): int
    {
        [$base, $user] = self::base($flags);
        $dest = self::resolve($base, $flags['path'] ?? '');

        $src = $flags['src'] ?? '';
        $srcReal = realpath($src);
        if (!$ctx->dryRun) {
            if ($srcReal === false || !str_starts_with($srcReal, self::UPLOAD_DIR . '/')) {
                throw new InvalidArgumentException('Upload source must be inside ' . self::UPLOAD_DIR);
            }
            if (!rename($srcReal, $dest)) {
                // cross-device fallback
                if (!copy($srcReal, $dest)) {
                    throw new RuntimeException('Import failed.');
                }
                unlink($srcReal);
            }
            self::own($ctx, $dest, $user);
        } else {
            $ctx->out("[dry-run] import {$src} -> {$dest}");
        }
        $ctx->out('Uploaded ' . basename($dest));
        return 0;
    }

    public static function mkdir(Ctx $ctx, array $flags): int
    {
        [$base, $user] = self::base($flags);
        $dir = self::resolve($base, $flags['path'] ?? '');
        if ($ctx->dryRun) {
            $ctx->out('[dry-run] mkdir ' . $dir);
            return 0;
        }
        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('mkdir failed.');
        }
        self::own($ctx, $dir, $user);
        $ctx->out('Created folder.');
        return 0;
    }

    public static function delete(Ctx $ctx, array $flags): int
    {
        [$base] = self::base($flags);
        $target = self::resolve($base, $flags['path'] ?? '', true);
        if (rtrim($target, '/') === rtrim($base, '/')) {
            throw new InvalidArgumentException('Refusing to delete the site root.');
        }
        $ctx->deletePath($target);
        $ctx->out('Deleted.');
        return 0;
    }

    public static function rename(Ctx $ctx, array $flags): int
    {
        [$base, $user] = self::base($flags);
        $from = self::resolve($base, $flags['from'] ?? '', true);
        $to = self::resolve($base, $flags['to'] ?? '');
        if ($ctx->dryRun) {
            $ctx->out("[dry-run] mv {$from} {$to}");
            return 0;
        }
        if (!rename($from, $to)) {
            throw new RuntimeException('Rename/move failed.');
        }
        self::own($ctx, $to, $user);
        $ctx->out('Moved.');
        return 0;
    }

    public static function copy(Ctx $ctx, array $flags): int
    {
        [$base, $user] = self::base($flags);
        $from = self::resolve($base, $flags['from'] ?? '', true);
        $to = self::resolve($base, $flags['to'] ?? '');
        $ctx->run(['cp', '-a', '--', $from, $to]);
        self::own($ctx, $to, $user);
        $ctx->out('Copied.');
        return 0;
    }

    public static function chmod(Ctx $ctx, array $flags): int
    {
        [$base] = self::base($flags);
        $target = self::resolve($base, $flags['path'] ?? '', true);
        $mode = $flags['mode'] ?? '';
        if (!preg_match('/^[0-7]{3}$/', $mode)) {
            throw new InvalidArgumentException('Mode must be 3 octal digits, e.g. 644.');
        }
        if ($ctx->dryRun) {
            $ctx->out("[dry-run] chmod {$mode} {$target}");
            return 0;
        }
        chmod($target, octdec($mode));
        $ctx->out("Permissions set to {$mode}.");
        return 0;
    }

    /** Compress paths (JSON list on stdin) into a .zip or .tar.gz archive. */
    public static function compress(Ctx $ctx, array $flags): int
    {
        [$base, $user] = self::base($flags);
        $destRel = $flags['dest'] ?? '';
        $dest = self::resolve($base, $destRel);

        $paths = json_decode($ctx->dryRun ? '[]' : $ctx->stdin(), true);
        if (!is_array($paths)) {
            $paths = [];
        }
        $relPaths = [];
        $cwd = self::resolve($base, $flags['path'] ?? '/', true); // directory the items live in
        foreach ($paths as $p) {
            $abs = self::resolve($base, (string) $p, !$ctx->dryRun);
            if (!str_starts_with($abs, $cwd . '/') && $abs !== $cwd) {
                throw new InvalidArgumentException('All items must be inside the current folder.');
            }
            $relPaths[] = substr($abs, strlen($cwd) + 1);
        }
        if ($relPaths === [] && !$ctx->dryRun) {
            throw new InvalidArgumentException('Nothing selected.');
        }

        if (str_ends_with($destRel, '.zip')) {
            $ctx->run(array_merge(['zip', '-qry', $dest, '--'], $relPaths), null, false, $cwd);
        } elseif (str_ends_with($destRel, '.tar.gz')) {
            $ctx->run(array_merge(['tar', 'czf', $dest, '--'], $relPaths), null, false, $cwd);
        } else {
            throw new InvalidArgumentException('Archive name must end with .zip or .tar.gz');
        }
        self::own($ctx, $dest, $user);
        $ctx->out('Archive created: ' . basename($dest));
        return 0;
    }

    public static function extract(Ctx $ctx, array $flags): int
    {
        [$base, $user] = self::base($flags);
        $archive = self::resolve($base, $flags['path'] ?? '', true);
        $destDir = self::resolve($base, $flags['dest'] ?? dirname($flags['path'] ?? '/'));

        if (!$ctx->dryRun && !is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        $name = strtolower($archive);
        if (str_ends_with($name, '.zip')) {
            $ctx->run(['unzip', '-qo', $archive, '-d', $destDir]);
        } elseif (str_ends_with($name, '.tar.gz') || str_ends_with($name, '.tgz')) {
            $ctx->run(['tar', 'xzf', $archive, '-C', $destDir]);
        } elseif (str_ends_with($name, '.tar')) {
            $ctx->run(['tar', 'xf', $archive, '-C', $destDir]);
        } else {
            throw new InvalidArgumentException('Supported archives: .zip, .tar.gz, .tgz, .tar');
        }
        self::own($ctx, $destDir, $user, true);
        $ctx->out('Extracted to ' . ($flags['dest'] ?? '.'));
        return 0;
    }

    // ---------------------------------------------------------------- helpers

    /** @return array{0: string, 1: string} [base path, system user] */
    private static function base(array $flags): array
    {
        $domain = Validate::domain($flags['domain'] ?? '');
        return ['/var/www/' . $domain, Validate::systemUserFor($domain)];
    }

    /**
     * Join a user-supplied relative path onto the site base, rejecting
     * traversal. With $mustExist the resolved path must exist and its
     * realpath must stay inside the base (catches symlink escapes).
     */
    private static function resolve(string $base, string $rel, bool $mustExist = false): string
    {
        $clean = [];
        foreach (explode('/', str_replace('\\', '/', $rel)) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..' || str_contains($segment, "\0")) {
                throw new InvalidArgumentException('Invalid path.');
            }
            $clean[] = $segment;
        }
        $path = rtrim($base . '/' . implode('/', $clean), '/');
        if ($path === '') {
            $path = $base;
        }

        if ($mustExist) {
            $baseReal = realpath($base);
            $real = realpath($path);
            if ($baseReal === false || $real === false
                || ($real !== $baseReal && !str_starts_with($real, $baseReal . '/'))) {
                throw new InvalidArgumentException('Path not found or outside the site.');
            }
            return $real;
        }

        // New paths: the parent must exist inside the base.
        $parentReal = realpath(dirname($path));
        $baseReal = realpath($base);
        if ($baseReal === false || $parentReal === false
            || ($parentReal !== $baseReal && !str_starts_with($parentReal, $baseReal . '/'))) {
            throw new InvalidArgumentException('Parent folder not found or outside the site.');
        }
        return $parentReal . '/' . basename($path);
    }

    private static function own(Ctx $ctx, string $path, string $user, bool $recursive = false): void
    {
        $argv = $recursive ? ['chown', '-R', "{$user}:{$user}", $path] : ['chown', "{$user}:{$user}", $path];
        $ctx->run($argv, null, true);
    }
}
