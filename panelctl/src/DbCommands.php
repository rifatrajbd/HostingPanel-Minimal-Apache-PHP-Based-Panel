<?php

declare(strict_types=1);

final class DbCommands
{
    /** @param array<string, string> $flags */
    public static function create(Ctx $ctx, array $flags): int
    {
        $name = Validate::dbName($flags['name'] ?? '');
        $user = Validate::dbUser($flags['user'] ?? '');
        $password = Validate::dbPassword($ctx->readSecret());

        // Identifiers and password are strictly validated above, so this
        // SQL cannot be injected into.
        $ctx->mysql('mysql', "CREATE DATABASE IF NOT EXISTS `{$name}`
            CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $ctx->mysql('mysql', "CREATE USER IF NOT EXISTS '{$user}'@'localhost' IDENTIFIED BY '{$password}'");
        $ctx->mysql('mysql', "GRANT ALL PRIVILEGES ON `{$name}`.* TO '{$user}'@'localhost'");
        $ctx->mysql('mysql', 'FLUSH PRIVILEGES');

        $ctx->out("Database {$name} created with user {$user}@localhost.");
        return 0;
    }

    /** @param array<string, string> $flags */
    public static function delete(Ctx $ctx, array $flags): int
    {
        $name = Validate::dbName($flags['name'] ?? '');
        $user = Validate::dbUser($flags['user'] ?? '');

        $ctx->mysql('mysql', "DROP DATABASE IF EXISTS `{$name}`");
        $ctx->mysql('mysql', "DROP USER IF EXISTS '{$user}'@'localhost'");
        $ctx->mysql('mysql', 'FLUSH PRIVILEGES');

        $ctx->out("Database {$name} and user {$user} dropped.");
        return 0;
    }

    /** Size (MB) and table count of every managed database, as JSON. */
    public static function list(Ctx $ctx, array $flags): int
    {
        if ($ctx->dryRun) {
            $ctx->out('{}');
            return 0;
        }
        $out = $ctx->mysql('information_schema',
            "SELECT table_schema, ROUND(COALESCE(SUM(data_length+index_length),0)/1048576,2), COUNT(table_name)
             FROM information_schema.tables
             WHERE table_schema NOT IN ('mysql','information_schema','performance_schema','sys','powerdns','mailserver')
             GROUP BY table_schema");
        $map = [];
        foreach (explode("\n", trim($out)) as $line) {
            if ($line === '') {
                continue;
            }
            [$schema, $size, $tables] = array_pad(explode("\t", $line), 3, '0');
            $map[$schema] = ['size_mb' => (float) $size, 'tables' => (int) $tables];
        }
        $ctx->out((string) json_encode($map));
        return 0;
    }

    /** Detailed info for one database as JSON (charset, collation, counts, size). */
    public static function info(Ctx $ctx, array $flags): int
    {
        $name = Validate::dbName($flags['name'] ?? '');
        if ($ctx->dryRun) {
            $ctx->out('{}');
            return 0;
        }

        $charset = explode("\t", trim($ctx->mysql('information_schema',
            "SELECT default_character_set_name, default_collation_name
             FROM information_schema.schemata WHERE schema_name='{$name}'")) ?: "\t");
        $counts = explode("\t", trim($ctx->mysql('information_schema',
            "SELECT
                COALESCE(SUM(table_type='BASE TABLE'),0),
                COALESCE(SUM(table_type='VIEW'),0),
                ROUND(COALESCE(SUM(data_length+index_length),0)/1048576,2)
             FROM information_schema.tables WHERE table_schema='{$name}'")) ?: "0\t0\t0");
        $routines = (int) trim($ctx->mysql('information_schema',
            "SELECT COUNT(*) FROM information_schema.routines WHERE routine_schema='{$name}'"));
        $triggers = (int) trim($ctx->mysql('information_schema',
            "SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema='{$name}'"));
        $events = (int) trim($ctx->mysql('information_schema',
            "SELECT COUNT(*) FROM information_schema.events WHERE event_schema='{$name}'"));

        $ctx->out((string) json_encode([
            'charset' => $charset[0] ?? 'utf8mb4',
            'collation' => $charset[1] ?? '',
            'tables' => (int) ($counts[0] ?? 0),
            'views' => (int) ($counts[1] ?? 0),
            'size_mb' => (float) ($counts[2] ?? 0),
            'routines' => $routines,
            'triggers' => $triggers,
            'events' => $events,
        ]));
        return 0;
    }

    public static function check(Ctx $ctx, array $flags): int
    {
        return self::maintenance($ctx, $flags, '--check', 'checked');
    }

    public static function repair(Ctx $ctx, array $flags): int
    {
        return self::maintenance($ctx, $flags, '--repair', 'repaired');
    }

    public static function optimize(Ctx $ctx, array $flags): int
    {
        return self::maintenance($ctx, $flags, '--optimize', 'optimized');
    }

    /** @param array<string, string> $flags */
    private static function maintenance(Ctx $ctx, array $flags, string $op, string $verb): int
    {
        $name = Validate::dbName($flags['name'] ?? '');
        $out = $ctx->run(['mysqlcheck', $op, $name], null, true);
        $ctx->out(trim($out) !== '' ? trim($out) : "Database {$name} {$verb}.");
        return 0;
    }

    /** Change an existing user's privilege level on a database. */
    public static function userPrivileges(Ctx $ctx, array $flags): int
    {
        $name = Validate::dbName($flags['name'] ?? '');
        $user = Validate::dbUser($flags['user'] ?? '');
        $priv = ($flags['privileges'] ?? 'all') === 'readonly' ? 'readonly' : 'all';
        $grant = $priv === 'readonly' ? 'SELECT' : 'ALL PRIVILEGES';

        $ctx->mysql('mysql', "REVOKE ALL PRIVILEGES ON `{$name}`.* FROM '{$user}'@'localhost'");
        $ctx->mysql('mysql', "GRANT {$grant} ON `{$name}`.* TO '{$user}'@'localhost'");
        $ctx->mysql('mysql', 'FLUSH PRIVILEGES');
        $ctx->out("{$user} now has {$priv} access on {$name}.");
        return 0;
    }

    /** Reset a database user's password (new password on stdin). */
    public static function password(Ctx $ctx, array $flags): int
    {
        $user = Validate::dbUser($flags['user'] ?? '');
        $password = Validate::dbPassword($ctx->readSecret());
        $ctx->mysql('mysql', "ALTER USER '{$user}'@'localhost' IDENTIFIED BY '{$password}'");
        $ctx->mysql('mysql', 'FLUSH PRIVILEGES');
        $ctx->out("Password updated for {$user}@localhost.");
        return 0;
    }

    /** Dump a database to a file (.sql or .sql.gz) and print its path. */
    public static function export(Ctx $ctx, array $flags): int
    {
        $name = Validate::dbName($flags['name'] ?? '');
        $gzip = ($flags['format'] ?? 'gz') !== 'sql';
        $dir = '/var/lib/hostingpanel/exports';
        $file = "{$dir}/{$name}-" . date('Ymd-His') . ($gzip ? '.sql.gz' : '.sql');

        if ($ctx->dryRun) {
            $ctx->out($file);
            return 0;
        }
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $ctx->run(['chown', 'hostingpanel:hostingpanel', $dir], null, true);
        $dump = 'mysqldump --single-transaction --quick --routines --events -- ' . escapeshellarg($name);
        $ctx->run(['bash', '-c',
            $dump . ($gzip ? ' | gzip' : '') . ' > ' . escapeshellarg($file)]);
        $ctx->run(['chown', 'hostingpanel:hostingpanel', $file], null, true);
        $ctx->run(['chmod', '640', $file], null, true);
        $ctx->out($file);
        return 0;
    }

    /** Load a staged .sql or .sql.gz upload into a database. */
    public static function import(Ctx $ctx, array $flags): int
    {
        $name = Validate::dbName($flags['name'] ?? '');
        $src = $flags['src'] ?? '';

        if ($ctx->dryRun) {
            $ctx->out("[dry-run] import upload into {$name}");
            return 0;
        }
        $real = realpath($src);
        if ($real === false || !str_starts_with($real, '/var/lib/hostingpanel/uploads/')) {
            throw new InvalidArgumentException('Import source must be a staged upload.');
        }
        $cmd = str_ends_with(strtolower($real), '.gz')
            ? 'gunzip -c ' . escapeshellarg($real) . ' | mysql ' . escapeshellarg($name)
            : 'mysql ' . escapeshellarg($name) . ' < ' . escapeshellarg($real);
        $ctx->run(['bash', '-c', $cmd]);
        @unlink($real);
        $ctx->out("Imported into database {$name}.");
        return 0;
    }

    /** Add an extra user to a database with a privilege level (password on stdin). */
    public static function userAdd(Ctx $ctx, array $flags): int
    {
        $name = Validate::dbName($flags['name'] ?? '');
        $user = Validate::dbUser($flags['user'] ?? '');
        $priv = ($flags['privileges'] ?? 'all') === 'readonly' ? 'readonly' : 'all';
        $password = Validate::dbPassword($ctx->readSecret());
        $grant = $priv === 'readonly' ? 'SELECT' : 'ALL PRIVILEGES';

        $ctx->mysql('mysql', "CREATE USER IF NOT EXISTS '{$user}'@'localhost' IDENTIFIED BY '{$password}'");
        $ctx->mysql('mysql', "ALTER USER '{$user}'@'localhost' IDENTIFIED BY '{$password}'");
        $ctx->mysql('mysql', "GRANT {$grant} ON `{$name}`.* TO '{$user}'@'localhost'");
        $ctx->mysql('mysql', 'FLUSH PRIVILEGES');
        $ctx->out("User {$user} granted {$priv} access on {$name}.");
        return 0;
    }

    /** @param array<string, string> $flags */
    public static function userDelete(Ctx $ctx, array $flags): int
    {
        $user = Validate::dbUser($flags['user'] ?? '');
        $ctx->mysql('mysql', "DROP USER IF EXISTS '{$user}'@'localhost'");
        $ctx->mysql('mysql', 'FLUSH PRIVILEGES');
        $ctx->out("Database user {$user} dropped.");
        return 0;
    }
}
