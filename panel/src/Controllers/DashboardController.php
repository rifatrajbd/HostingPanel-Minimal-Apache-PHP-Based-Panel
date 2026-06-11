<?php

declare(strict_types=1);

namespace Panel\Controllers;

use Panel\Database;
use Panel\Services\SystemStats;
use Panel\Support\View;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class DashboardController extends Controller
{
    public function __construct(
        View $view,
        private readonly Database $db,
        private readonly SystemStats $stats
    ) {
        parent::__construct($view);
    }

    public function index(Request $request, Response $response): Response
    {
        $counts = [
            'sites' => (int) ($this->db->one('SELECT COUNT(*) AS n FROM sites')['n'] ?? 0),
            'databases' => (int) ($this->db->one('SELECT COUNT(*) AS n FROM site_databases')['n'] ?? 0),
            'mailboxes' => (int) ($this->db->one('SELECT COUNT(*) AS n FROM mailboxes')['n'] ?? 0),
        ];

        return $this->html($response, 'dashboard', [
            'title' => 'Dashboard',
            'active' => 'dashboard',
            'counts' => $counts,
            'stats' => $this->stats->snapshot(),
            'recent' => $this->db->all(
                'SELECT action, details, ip, created_at FROM audit_log ORDER BY id DESC LIMIT 8'
            ),
        ]);
    }
}
