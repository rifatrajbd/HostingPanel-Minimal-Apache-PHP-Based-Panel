<?php

declare(strict_types=1);

namespace Panel\Controllers;

use Panel\Database;
use Panel\Services\PanelCtl;
use Panel\Support\Validator;
use Panel\Support\View;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class SitesController extends Controller
{
    /** @param array<string, mixed> $settings */
    public function __construct(
        View $view,
        private readonly Database $db,
        private readonly PanelCtl $ctl,
        private readonly array $settings
    ) {
        parent::__construct($view);
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->html($response, 'sites/index', [
            'title' => 'Sites',
            'active' => 'sites',
            'sites' => $this->db->all('SELECT * FROM sites ORDER BY domain'),
        ]);
    }

    public function createForm(Request $request, Response $response): Response
    {
        return $this->html($response, 'sites/create', [
            'title' => 'Add site',
            'active' => 'sites',
            'phpVersions' => $this->settings['php_versions'],
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        $domain = strtolower($this->input($request, 'domain'));
        $php = $this->input($request, 'php_version');

        if (!Validator::domain($domain)) {
            $this->flash('error', 'Invalid domain name.');
            return $this->redirect($response, '/sites/create');
        }
        if (!Validator::phpVersion($php, $this->settings['php_versions'])) {
            $this->flash('error', 'Unsupported PHP version.');
            return $this->redirect($response, '/sites/create');
        }
        if ($this->db->one('SELECT id FROM sites WHERE domain = ?', [$domain]) !== null) {
            $this->flash('error', 'Site already exists.');
            return $this->redirect($response, '/sites/create');
        }

        $result = $this->ctl->run('site:create', ['domain' => $domain, 'php' => $php]);
        if (!$result->ok()) {
            $this->flash('error', 'site:create failed: ' . $result->output());
            return $this->redirect($response, '/sites/create');
        }

        $systemUser = 'web-' . substr(preg_replace('/[^a-z0-9]/', '', $domain) ?? '', 0, 24);
        $this->db->run(
            'INSERT INTO sites (domain, php_version, doc_root, system_user, created_at)
             VALUES (?, ?, ?, ?, ?)',
            [$domain, $php, "/var/www/{$domain}/htdocs", $systemUser, time()]
        );
        $this->db->audit($this->userId(), 'site.create', $domain, $this->ip($request));
        $this->flash('success', "Site {$domain} created (PHP {$php}). " . $result->output());
        return $this->redirect($response, '/sites');
    }

    public function issueSsl(Request $request, Response $response, array $args): Response
    {
        $site = $this->db->one('SELECT * FROM sites WHERE id = ?', [(int) $args['id']]);
        if ($site === null) {
            return $this->redirect($response, '/sites');
        }

        $flags = ['domain' => (string) $site['domain']];
        if ($this->input($request, 'include_www') === '1') {
            $flags['www'] = '1';
        }

        $result = $this->ctl->run('ssl:issue', $flags);
        if ($result->ok()) {
            $this->db->run('UPDATE sites SET ssl_enabled = 1 WHERE id = ?', [$site['id']]);
            $this->db->audit($this->userId(), 'ssl.issue', (string) $site['domain'], $this->ip($request));
            $this->flash('success', "SSL certificate issued for {$site['domain']}.");
        } else {
            $this->flash('error', 'ssl:issue failed: ' . $result->output());
        }
        return $this->redirect($response, '/sites');
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $site = $this->db->one('SELECT * FROM sites WHERE id = ?', [(int) $args['id']]);
        if ($site === null) {
            return $this->redirect($response, '/sites');
        }
        $ini = json_decode((string) ($site['ini_json'] ?? '{}'), true);

        return $this->html($response, 'sites/show', [
            'title' => $site['domain'],
            'active' => 'sites',
            'site' => $site,
            'ini' => is_array($ini) ? $ini : [],
            'phpVersions' => $this->settings['php_versions'],
            'cronJobs' => $this->db->all(
                'SELECT * FROM cron_jobs WHERE site_id = ? ORDER BY id',
                [$site['id']]
            ),
        ]);
    }

    public function updatePhp(Request $request, Response $response, array $args): Response
    {
        $site = $this->db->one('SELECT * FROM sites WHERE id = ?', [(int) $args['id']]);
        if ($site === null) {
            return $this->redirect($response, '/sites');
        }
        $newVersion = $this->input($request, 'php_version');
        if (!Validator::phpVersion($newVersion, $this->settings['php_versions'])) {
            $this->flash('error', 'Unsupported PHP version.');
            return $this->redirect($response, '/sites/' . $site['id']);
        }

        $ini = [];
        foreach (['memory_limit', 'upload_max_filesize', 'post_max_size'] as $key) {
            $value = strtoupper($this->input($request, $key));
            if (preg_match('/^\d{1,5}M$/', $value)) {
                $ini[$key] = $value;
            }
        }
        $maxExec = $this->input($request, 'max_execution_time');
        if (preg_match('/^\d{1,4}$/', $maxExec)) {
            $ini['max_execution_time'] = $maxExec;
        }
        $ini['display_errors'] = $this->input($request, 'display_errors') === 'on' ? 'on' : 'off';

        $iniJson = (string) json_encode($ini);
        $oldVersion = (string) $site['php_version'];
        $result = $oldVersion !== $newVersion
            ? $this->ctl->run('site:php', [
                'domain' => (string) $site['domain'], 'old' => $oldVersion, 'new' => $newVersion,
            ], $iniJson)
            : $this->ctl->run('site:ini', [
                'domain' => (string) $site['domain'], 'php' => $newVersion,
            ], $iniJson);

        if ($result->ok()) {
            $this->db->run(
                'UPDATE sites SET php_version = ?, ini_json = ? WHERE id = ?',
                [$newVersion, $iniJson, $site['id']]
            );
            $this->db->audit($this->userId(), 'site.php', "{$site['domain']} -> {$newVersion}", $this->ip($request));
        }
        $this->flash($result->ok() ? 'success' : 'error', $result->output());
        return $this->redirect($response, '/sites/' . $site['id']);
    }

    public function toggleCf(Request $request, Response $response, array $args): Response
    {
        $site = $this->db->one('SELECT * FROM sites WHERE id = ?', [(int) $args['id']]);
        if ($site === null) {
            return $this->redirect($response, '/sites');
        }
        $enable = $this->input($request, 'enable') === '1';

        $result = $this->ctl->run('site:cfonly', [
            'domain' => (string) $site['domain'],
            'enable' => $enable ? '1' : '0',
        ]);
        if ($result->ok()) {
            $this->db->run('UPDATE sites SET cf_only = ? WHERE id = ?', [$enable ? 1 : 0, $site['id']]);
            $this->db->audit(
                $this->userId(),
                'site.cfonly',
                $site['domain'] . ($enable ? ' on' : ' off'),
                $this->ip($request)
            );
        }
        $this->flash($result->ok() ? 'success' : 'error', $result->output());
        return $this->redirect($response, '/sites/' . $site['id']);
    }

    public function cronAdd(Request $request, Response $response, array $args): Response
    {
        $site = $this->db->one('SELECT * FROM sites WHERE id = ?', [(int) $args['id']]);
        if ($site === null) {
            return $this->redirect($response, '/sites');
        }
        $schedule = trim(preg_replace('/\s+/', ' ', $this->input($request, 'schedule')) ?? '');
        $command = $this->input($request, 'command');

        $fields = explode(' ', $schedule);
        $scheduleOk = count($fields) === 5
            && array_filter($fields, fn ($f) => !preg_match('#^[0-9*,/\-]{1,20}$#', $f)) === [];
        if (!$scheduleOk) {
            $this->flash('error', 'Schedule must be 5 cron fields, e.g. "*/15 * * * *".');
            return $this->redirect($response, '/sites/' . $site['id']);
        }
        if ($command === '' || strlen($command) > 500 || str_contains($command, '%')) {
            $this->flash('error', 'Command must be one line under 500 chars without "%".');
            return $this->redirect($response, '/sites/' . $site['id']);
        }

        $this->db->run(
            'INSERT INTO cron_jobs (site_id, schedule, command, created_at) VALUES (?, ?, ?, ?)',
            [$site['id'], $schedule, $command, time()]
        );
        $this->syncCron($request, $site);
        return $this->redirect($response, '/sites/' . $site['id']);
    }

    public function cronDelete(Request $request, Response $response, array $args): Response
    {
        $site = $this->db->one('SELECT * FROM sites WHERE id = ?', [(int) $args['id']]);
        if ($site === null) {
            return $this->redirect($response, '/sites');
        }
        $this->db->run(
            'DELETE FROM cron_jobs WHERE id = ? AND site_id = ?',
            [(int) $args['cid'], $site['id']]
        );
        $this->syncCron($request, $site);
        return $this->redirect($response, '/sites/' . $site['id']);
    }

    /** @param array<string, mixed> $site */
    private function syncCron(Request $request, array $site): void
    {
        $jobs = $this->db->all(
            'SELECT schedule, command FROM cron_jobs WHERE site_id = ? ORDER BY id',
            [$site['id']]
        );
        $result = $this->ctl->run(
            'cron:sync',
            ['domain' => (string) $site['domain']],
            (string) json_encode($jobs)
        );
        $this->flash($result->ok() ? 'success' : 'error', $result->output());
        if ($result->ok()) {
            $this->db->audit($this->userId(), 'site.cron', (string) $site['domain'], $this->ip($request));
        }
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $site = $this->db->one('SELECT * FROM sites WHERE id = ?', [(int) $args['id']]);
        if ($site === null) {
            return $this->redirect($response, '/sites');
        }
        if ($this->input($request, 'confirm_domain') !== $site['domain']) {
            $this->flash('error', 'Confirmation domain did not match — site not deleted.');
            return $this->redirect($response, '/sites');
        }

        $result = $this->ctl->run('site:delete', ['domain' => (string) $site['domain']]);
        if (!$result->ok()) {
            $this->flash('error', 'site:delete failed: ' . $result->output());
            return $this->redirect($response, '/sites');
        }

        $this->db->run('DELETE FROM sites WHERE id = ?', [$site['id']]);
        $this->db->audit($this->userId(), 'site.delete', (string) $site['domain'], $this->ip($request));
        $this->flash('success', "Site {$site['domain']} deleted.");
        return $this->redirect($response, '/sites');
    }
}
