<?php

declare(strict_types=1);

$env = getenv('PANEL_ENV') ?: 'production';
$isDev = $env === 'dev';

return [
    'env' => $env,
    'db_path' => getenv('PANEL_DB')
        ?: ($isDev || PHP_OS_FAMILY === 'Windows'
            ? dirname(__DIR__) . '/var/panel.sqlite'
            : '/var/lib/hostingpanel/panel.sqlite'),
    'panelctl_bin' => getenv('PANELCTL_BIN') ?: '/usr/local/bin/panelctl',
    'panelctl_dev_script' => dirname(__DIR__, 2) . '/panelctl/panelctl',
    'php_versions' => ['7.4', '8.1', '8.2', '8.3'],
    'session_name' => 'hp_session',
    'login_max_attempts' => 5,
    'login_window_sec' => 900,
];
