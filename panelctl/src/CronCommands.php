<?php

declare(strict_types=1);

/**
 * Per-site cron jobs. The panel sends the complete desired job list
 * (JSON on stdin) and we write /etc/cron.d/hostingpanel-<user>;
 * jobs run as the site's own system user.
 */
final class CronCommands
{
    public static function sync(Ctx $ctx, array $flags): int
    {
        $domain = Validate::domain($flags['domain'] ?? '');
        $user = Validate::systemUserFor($domain);
        $file = "/etc/cron.d/hostingpanel-{$user}";

        $raw = $ctx->dryRun ? '[]' : $ctx->stdin();
        $jobs = json_decode($raw === '' ? '[]' : $raw, true);
        if (!is_array($jobs)) {
            throw new InvalidArgumentException('Expected a JSON array of jobs on stdin.');
        }

        $lines = [
            '# Managed by HostingPanel — do not edit by hand.',
            'SHELL=/bin/bash',
            'PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
            'MAILTO=""',
        ];
        foreach ($jobs as $job) {
            $schedule = self::validSchedule((string) ($job['schedule'] ?? ''));
            $command = self::validCommand((string) ($job['command'] ?? ''));
            $lines[] = "{$schedule} {$user} {$command}";
        }

        if (count($jobs) === 0) {
            $ctx->deletePath($file);
            $ctx->out("No cron jobs — removed {$file}.");
        } else {
            $ctx->writeFile($file, implode("\n", $lines) . "\n", 0644);
            $ctx->out(count($jobs) . " cron job(s) written for {$domain}.");
        }
        return 0;
    }

    public static function remove(Ctx $ctx, array $flags): int
    {
        $domain = Validate::domain($flags['domain'] ?? '');
        $ctx->deletePath('/etc/cron.d/hostingpanel-' . Validate::systemUserFor($domain));
        $ctx->out("Cron file removed for {$domain}.");
        return 0;
    }

    public static function validSchedule(string $schedule): string
    {
        $schedule = trim(preg_replace('/\s+/', ' ', $schedule) ?? '');
        $fields = explode(' ', $schedule);
        if (count($fields) !== 5) {
            throw new InvalidArgumentException('Schedule needs 5 fields (min hour day month weekday).');
        }
        foreach ($fields as $field) {
            if (!preg_match('#^[0-9*,/\-]{1,20}$#', $field)) {
                throw new InvalidArgumentException("Invalid schedule field: {$field}");
            }
        }
        return $schedule;
    }

    public static function validCommand(string $command): string
    {
        $command = trim($command);
        if ($command === '' || strlen($command) > 500
            || str_contains($command, "\n") || str_contains($command, '%')) {
            throw new InvalidArgumentException(
                'Command must be a single line under 500 chars without "%" (cron limitation).'
            );
        }
        return $command;
    }
}
