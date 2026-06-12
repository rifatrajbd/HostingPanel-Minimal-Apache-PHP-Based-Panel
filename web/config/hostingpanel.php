<?php

return [
    // Dev mode runs panelctl with --dry-run (no real system changes).
    'dev' => env('PANEL_DEV', false),

    // Production: the panel talks to the root panelctld daemon over this
    // private Unix socket — no sudo anywhere.
    'socket' => env('PANELCTL_SOCKET', '/run/hostingpanel/panelctl.sock'),

    // Dev only: the CLI is invoked with --dry-run instead of the socket.
    'panelctl_dev' => env('PANELCTL_DEV', base_path('../panelctl/panelctl')),

    'php_versions' => ['7.4', '8.1', '8.2', '8.3'],

    // Staging dir for file-manager uploads (panel user must own it).
    'uploads' => env('PANEL_UPLOADS', '/var/lib/hostingpanel/uploads'),

    // Failed panel logins are appended here for the fail2ban jail to watch.
    'auth_log' => env('PANEL_AUTH_LOG', '/var/log/hostingpanel/auth.log'),
];
