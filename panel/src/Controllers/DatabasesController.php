<?php

declare(strict_types=1);

namespace Panel\Controllers;

use Panel\Database;
use Panel\Services\PanelCtl;
use Panel\Support\Validator;
use Panel\Support\View;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class DatabasesController extends Controller
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
        return $this->html($response, 'databases/index', [
            'title' => 'Databases',
            'active' => 'databases',
            'databases' => $this->db->all('SELECT * FROM site_databases ORDER BY name'),
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        $name = strtolower($this->input($request, 'name'));
        $user = strtolower($this->input($request, 'db_user'));

        if (!Validator::dbName($name) || !Validator::dbUser($user)) {
            $this->flash('error', 'Names must be 3-32 chars: lowercase letters, digits, underscores; start with a letter.');
            return $this->redirect($response, '/databases');
        }
        if ($this->db->one('SELECT id FROM site_databases WHERE name = ?', [$name]) !== null) {
            $this->flash('error', 'Database already exists.');
            return $this->redirect($response, '/databases');
        }

        $password = Validator::randomPassword();
        $result = $this->ctl->run('db:create', ['name' => $name, 'user' => $user], $password . "\n");
        if (!$result->ok()) {
            $this->flash('error', 'db:create failed: ' . $result->output());
            return $this->redirect($response, '/databases');
        }

        $this->db->run(
            'INSERT INTO site_databases (name, db_user, created_at) VALUES (?, ?, ?)',
            [$name, $user, time()]
        );
        $this->db->audit($this->userId(), 'db.create', $name, $this->ip($request));
        $this->flash(
            'success',
            "Database \"{$name}\" created. User: {$user} — Password: {$password} — "
            . 'save it now, it will not be shown again.'
        );
        return $this->redirect($response, '/databases');
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $row = $this->db->one('SELECT * FROM site_databases WHERE id = ?', [(int) $args['id']]);
        if ($row === null) {
            return $this->redirect($response, '/databases');
        }
        if ($this->input($request, 'confirm_name') !== $row['name']) {
            $this->flash('error', 'Confirmation name did not match — database not deleted.');
            return $this->redirect($response, '/databases');
        }

        $result = $this->ctl->run('db:delete', [
            'name' => (string) $row['name'],
            'user' => (string) $row['db_user'],
        ]);
        if (!$result->ok()) {
            $this->flash('error', 'db:delete failed: ' . $result->output());
            return $this->redirect($response, '/databases');
        }

        $this->db->run('DELETE FROM site_databases WHERE id = ?', [$row['id']]);
        $this->db->audit($this->userId(), 'db.delete', (string) $row['name'], $this->ip($request));
        $this->flash('success', "Database {$row['name']} deleted.");
        return $this->redirect($response, '/databases');
    }
}
