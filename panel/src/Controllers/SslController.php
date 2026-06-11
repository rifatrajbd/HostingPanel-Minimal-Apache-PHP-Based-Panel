<?php

declare(strict_types=1);

namespace Panel\Controllers;

use Panel\Database;
use Panel\Services\PanelCtl;
use Panel\Support\Validator;
use Panel\Support\View;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class SslController extends Controller
{
    public function __construct(
        View $view,
        private readonly Database $db,
        private readonly PanelCtl $ctl
    ) {
        parent::__construct($view);
    }

    public function index(Request $request, Response $response): Response
    {
        $result = $this->ctl->run('ssl:list');
        $certs = json_decode($result->stdout, true);

        return $this->html($response, 'ssl/index', [
            'title' => 'SSL Manager',
            'active' => 'ssl',
            'certs' => is_array($certs) ? $certs : [],
            'sites' => $this->db->all('SELECT * FROM sites WHERE ssl_enabled = 0 ORDER BY domain'),
        ]);
    }

    public function renew(Request $request, Response $response): Response
    {
        $domain = strtolower($this->input($request, 'domain'));
        if (!Validator::domain($domain)) {
            return $this->redirect($response, '/ssl');
        }
        $result = $this->ctl->run('ssl:renew', ['domain' => $domain]);
        $this->flash($result->ok() ? 'success' : 'error', $result->output());
        $this->db->audit($this->userId(), 'ssl.renew', $domain, $this->ip($request));
        return $this->redirect($response, '/ssl');
    }

    public function delete(Request $request, Response $response): Response
    {
        $domain = strtolower($this->input($request, 'domain'));
        if (!Validator::domain($domain) || $this->input($request, 'confirm_domain') !== $domain) {
            $this->flash('error', 'Confirmation domain did not match.');
            return $this->redirect($response, '/ssl');
        }
        $result = $this->ctl->run('ssl:delete', ['domain' => $domain]);
        if ($result->ok()) {
            $this->db->run('UPDATE sites SET ssl_enabled = 0 WHERE domain = ?', [$domain]);
        }
        $this->flash($result->ok() ? 'success' : 'error', $result->output());
        $this->db->audit($this->userId(), 'ssl.delete', $domain, $this->ip($request));
        return $this->redirect($response, '/ssl');
    }
}
