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
}
