<?php

declare(strict_types=1);

namespace Panel\Controllers;

use Panel\Database;
use Panel\Services\PanelCtl;
use Panel\Support\Validator;
use Panel\Support\View;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class SettingsController extends Controller
{
    private const SCHEDULES = [
        'daily' => '0 3 * * *',
        'twice-daily' => '0 3,15 * * *',
        'weekly' => '0 3 * * 0',
        'disabled' => '',
    ];

    public function __construct(
        View $view,
        private readonly Database $db,
        private readonly PanelCtl $ctl
    ) {
        parent::__construct($view);
    }

    public function index(Request $request, Response $response): Response
    {
        $logResult = $this->ctl->run('backup:log');

        return $this->html($response, 'settings/index', [
            'title' => 'Settings',
            'active' => 'settings',
            'panelDomain' => $this->db->getSetting('panel_domain'),
            'backup' => [
                'type' => $this->db->getSetting('backup_type'),
                'host' => $this->db->getSetting('backup_host'),
                'user' => $this->db->getSetting('backup_user'),
                'path' => $this->db->getSetting('backup_path', 'hostingpanel-backups'),
                'retention' => $this->db->getSetting('backup_retention', '7'),
                'schedule' => $this->db->getSetting('backup_schedule', 'disabled'),
            ],
            'backupLog' => $logResult->ok() ? trim($logResult->stdout) : '',
        ]);
    }

    public function panelDomain(Request $request, Response $response): Response
    {
        $domain = strtolower($this->input($request, 'domain'));
        if (!Validator::domain($domain)) {
            $this->flash('error', 'Invalid domain.');
            return $this->redirect($response, '/settings');
        }

        $result = $this->ctl->run('panel:domain', ['domain' => $domain]);
        if ($result->ok()) {
            $this->db->setSetting('panel_domain', $domain);
            $this->flash('success', $result->output() . ' — reload this page at the new address.');
        } else {
            $this->flash('error', 'panel:domain failed: ' . $result->output()
                . ' (is the DNS A record for ' . $domain . ' pointing here?)');
        }
        $this->db->audit($this->userId(), 'panel.domain', $domain, $this->ip($request));
        return $this->redirect($response, '/settings');
    }

    public function backupSave(Request $request, Response $response): Response
    {
        $type = $this->input($request, 'type');
        $schedule = $this->input($request, 'schedule');
        $retention = (int) $this->input($request, 'retention', '7');
        $path = $this->input($request, 'path', 'hostingpanel-backups');

        if (!in_array($type, ['ftp', 'drive'], true) || !isset(self::SCHEDULES[$schedule])
            || $retention < 1 || $retention > 365) {
            $this->flash('error', 'Invalid backup settings.');
            return $this->redirect($response, '/settings');
        }

        $config = ['type' => $type, 'path' => $path, 'retention' => $retention];
        if ($type === 'ftp') {
            $config['host'] = $this->input($request, 'host');
            $config['user'] = $this->input($request, 'user');
            $config['pass'] = $this->input($request, 'pass');
        } else {
            $config['token'] = $this->input($request, 'token');
        }

        $result = $this->ctl->run('backup:config', [], (string) json_encode($config));
        if (!$result->ok()) {
            $this->flash('error', 'backup:config failed: ' . $result->output());
            return $this->redirect($response, '/settings');
        }

        $cron = self::SCHEDULES[$schedule];
        $scheduleResult = $cron === ''
            ? $this->ctl->run('backup:schedule', ['disable' => '1'])
            : $this->ctl->run('backup:schedule', ['cron' => $cron]);

        // Persist the non-secret parts for redisplay (never the password/token).
        $this->db->setSetting('backup_type', $type);
        $this->db->setSetting('backup_host', $type === 'ftp' ? $this->input($request, 'host') : '');
        $this->db->setSetting('backup_user', $type === 'ftp' ? $this->input($request, 'user') : '');
        $this->db->setSetting('backup_path', $path);
        $this->db->setSetting('backup_retention', (string) $retention);
        $this->db->setSetting('backup_schedule', $schedule);

        $this->flash(
            $scheduleResult->ok() ? 'success' : 'error',
            'Backup settings saved. ' . $scheduleResult->output()
        );
        $this->db->audit($this->userId(), 'backup.config', "{$type}, {$schedule}", $this->ip($request));
        return $this->redirect($response, '/settings');
    }

    public function backupTest(Request $request, Response $response): Response
    {
        $result = $this->ctl->run('backup:test');
        $this->flash($result->ok() ? 'success' : 'error', $result->output());
        return $this->redirect($response, '/settings');
    }

    public function backupRun(Request $request, Response $response): Response
    {
        $result = $this->ctl->run('backup:run');
        $this->flash($result->ok() ? 'success' : 'error', $result->output());
        $this->db->audit($this->userId(), 'backup.run', '', $this->ip($request));
        return $this->redirect($response, '/settings');
    }

    public function selfUpdate(Request $request, Response $response): Response
    {
        $result = $this->ctl->run('panel:self-update');
        $this->flash($result->ok() ? 'success' : 'error', $result->output());
        $this->db->audit($this->userId(), 'panel.self_update', '', $this->ip($request));
        return $this->redirect($response, '/settings');
    }
}
