<?php

return [
    // Dev mode runs panelctl with --dry-run (no real system changes).
    'dev' => env('PANEL_DEV', false),

    'panelctl_bin' => env('PANELCTL_BIN', '/usr/local/bin/panelctl'),
    'panelctl_dev' => env('PANELCTL_DEV', base_path('../panelctl/panelctl')),

    'php_versions' => ['7.4', '8.1', '8.2', '8.3'],

    // Staging dir for file-manager uploads (panel user must own it).
    'uploads' => env('PANEL_UPLOADS', '/var/lib/hostingpanel/uploads'),
];
