<?php

declare(strict_types=1);

/**
 * Shared bootstrap for panelctl — used by both the CLI entrypoint
 * (panelctl) and the socket daemon (panelctld). Loads all command
 * classes, defines the supported PHP versions, and exposes the
 * command map + a single dispatch() entrypoint.
 */

require __DIR__ . '/Ctx.php';
require __DIR__ . '/Validate.php';
require __DIR__ . '/Net.php';
require __DIR__ . '/SiteCommands.php';
require __DIR__ . '/DbCommands.php';
require __DIR__ . '/MailCommands.php';
require __DIR__ . '/FsCommands.php';
require __DIR__ . '/PhpCommands.php';
require __DIR__ . '/CronCommands.php';
require __DIR__ . '/SslCommands.php';
require __DIR__ . '/AccessCommands.php';
require __DIR__ . '/PanelCommands.php';
require __DIR__ . '/BackupCommands.php';
require __DIR__ . '/DnsCommands.php';
require __DIR__ . '/FtpCommands.php';

const PANELCTL_PHP_VERSIONS = ['7.4', '8.1', '8.2', '8.3'];

final class Dispatcher
{
    /** @return array<string, array{0: class-string, 1: string}> */
    public static function map(): array
    {
        return [
            'site:create' => [SiteCommands::class, 'create'],
            'site:delete' => [SiteCommands::class, 'delete'],
            'site:php' => [PhpCommands::class, 'sitePhp'],
            'site:ini' => [PhpCommands::class, 'siteIni'],
            'site:cfonly' => [AccessCommands::class, 'siteCfOnly'],
            'site:ipmode' => [AccessCommands::class, 'siteIpMode'],
            'site:aliases' => [SiteCommands::class, 'aliases'],
            'cf:update' => [AccessCommands::class, 'cfUpdate'],
            'ssl:issue' => [SslCommands::class, 'issue'],
            'ssl:wildcard' => [SslCommands::class, 'wildcard'],
            'ssl:list' => [SslCommands::class, 'list'],
            'ssl:renew' => [SslCommands::class, 'renew'],
            'ssl:delete' => [SslCommands::class, 'delete'],
            'dns:sync' => [DnsCommands::class, 'sync'],
            'dns:zone:delete' => [DnsCommands::class, 'zoneDelete'],
            'dns:acme' => [DnsCommands::class, 'acme'],
            'ftp:create' => [FtpCommands::class, 'create'],
            'ftp:password' => [FtpCommands::class, 'password'],
            'ftp:delete' => [FtpCommands::class, 'delete'],
            'db:create' => [DbCommands::class, 'create'],
            'db:delete' => [DbCommands::class, 'delete'],
            'db:list' => [DbCommands::class, 'list'],
            'db:password' => [DbCommands::class, 'password'],
            'db:export' => [DbCommands::class, 'export'],
            'db:import' => [DbCommands::class, 'import'],
            'db:user:add' => [DbCommands::class, 'userAdd'],
            'db:user:delete' => [DbCommands::class, 'userDelete'],
            'mail:domain:add' => [MailCommands::class, 'domainAdd'],
            'mail:domain:delete' => [MailCommands::class, 'domainDelete'],
            'mail:mailbox:add' => [MailCommands::class, 'mailboxAdd'],
            'mail:mailbox:delete' => [MailCommands::class, 'mailboxDelete'],
            'mail:queue' => [MailCommands::class, 'queue'],
            'mail:queue:flush' => [MailCommands::class, 'queueFlush'],
            'mail:queue:delete' => [MailCommands::class, 'queueDelete'],
            'mail:log' => [MailCommands::class, 'log'],
            'fs:list' => [FsCommands::class, 'list'],
            'fs:read' => [FsCommands::class, 'read'],
            'fs:write' => [FsCommands::class, 'write'],
            'fs:import' => [FsCommands::class, 'import'],
            'fs:mkdir' => [FsCommands::class, 'mkdir'],
            'fs:delete' => [FsCommands::class, 'delete'],
            'fs:rename' => [FsCommands::class, 'rename'],
            'fs:copy' => [FsCommands::class, 'copy'],
            'fs:chmod' => [FsCommands::class, 'chmod'],
            'fs:compress' => [FsCommands::class, 'compress'],
            'fs:extract' => [FsCommands::class, 'extract'],
            'php:ext-list' => [PhpCommands::class, 'extList'],
            'php:ext' => [PhpCommands::class, 'ext'],
            'cron:sync' => [CronCommands::class, 'sync'],
            'cron:remove' => [CronCommands::class, 'remove'],
            'panel:domain' => [PanelCommands::class, 'domain'],
            'panel:access' => [PanelCommands::class, 'access'],
            'panel:self-update' => [PanelCommands::class, 'selfUpdate'],
            'backup:config' => [BackupCommands::class, 'config'],
            'backup:schedule' => [BackupCommands::class, 'schedule'],
            'backup:test' => [BackupCommands::class, 'test'],
            'backup:run' => [BackupCommands::class, 'run'],
            'backup:log' => [BackupCommands::class, 'log'],
        ];
    }

    /**
     * Run one command and capture everything. Never throws — failures are
     * returned as [code, stdout, stderr] so both the CLI and the daemon can
     * report them uniformly.
     *
     * @param array<string, string> $flags
     * @return array{0: int, 1: string, 2: string} [exit code, stdout, stderr]
     */
    public static function dispatch(string $command, array $flags, string $stdin, bool $dryRun): array
    {
        $map = self::map();
        if (!isset($map[$command])) {
            return [2, '', "Unknown command: {$command}"];
        }

        $ctx = new Ctx($dryRun, dirname(__DIR__) . '/templates', $stdin);
        try {
            [$class, $method] = $map[$command];
            $code = (int) $class::$method($ctx, $flags);
            return [$code, $ctx->getOutput(), ''];
        } catch (Throwable $e) {
            return [1, $ctx->getOutput(), $e->getMessage()];
        }
    }
}
