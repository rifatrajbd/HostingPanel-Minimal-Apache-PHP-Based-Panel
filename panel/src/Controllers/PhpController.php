<?php

declare(strict_types=1);

namespace Panel\Controllers;

use Panel\Database;
use Panel\Services\PanelCtl;
use Panel\Support\View;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class PhpController extends Controller
{
    private const SUGGESTED_EXTS = [
        'imagick', 'redis', 'memcached', 'bcmath', 'soap', 'apcu', 'opcache', 'sqlite3',
    ];

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
        $versions = [];
        foreach ($this->settings['php_versions'] as $version) {
            $result = $this->ctl->run('php:ext-list', ['php' => $version]);
            $exts = json_decode($result->stdout, true);
            $versions[$version] = [
                'ok' => $result->ok() && is_array($exts),
                'extensions' => is_array($exts) ? $exts : [],
            ];
        }

        return $this->html($response, 'php/index', [
            'title' => 'PHP Manager',
            'active' => 'php',
            'versions' => $versions,
            'suggested' => self::SUGGESTED_EXTS,
            'dev' => $this->settings['env'] === 'dev',
        ]);
    }

    public function ext(Request $request, Response $response): Response
    {
        $version = $this->input($request, 'php');
        $name = strtolower($this->input($request, 'name'));
        $action = $this->input($request, 'action');

        if (!in_array($version, $this->settings['php_versions'], true)
            || !preg_match('/^[a-z0-9_]{2,30}$/', $name)
            || !in_array($action, ['install', 'enable', 'disable'], true)) {
            $this->flash('error', 'Invalid extension request.');
            return $this->redirect($response, '/php');
        }

        $result = $this->ctl->run('php:ext', ['php' => $version, 'name' => $name, 'action' => $action]);
        $this->flash($result->ok() ? 'success' : 'error', $result->output());
        if ($result->ok()) {
            $this->db->audit($this->userId(), 'php.ext', "{$action} {$name} (PHP {$version})", $this->ip($request));
        }
        return $this->redirect($response, '/php');
    }
}
