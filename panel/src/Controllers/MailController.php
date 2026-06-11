<?php

declare(strict_types=1);

namespace Panel\Controllers;

use Panel\Database;
use Panel\Services\PanelCtl;
use Panel\Support\Validator;
use Panel\Support\View;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class MailController extends Controller
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
        // Delivery queue: postqueue -j emits one JSON object per line.
        $queue = [];
        $queueResult = $this->ctl->run('mail:queue');
        if ($queueResult->ok()) {
            foreach (explode("\n", trim($queueResult->stdout)) as $line) {
                $entry = json_decode($line, true);
                if (is_array($entry) && isset($entry['queue_id'])) {
                    $queue[] = $entry;
                }
            }
        }
        $logResult = $this->ctl->run('mail:log');

        return $this->html($response, 'mail/index', [
            'title' => 'Mail',
            'active' => 'mail',
            'domains' => $this->db->all('SELECT * FROM mail_domains ORDER BY domain'),
            'mailboxes' => $this->db->all(
                'SELECT m.*, d.domain FROM mailboxes m
                 JOIN mail_domains d ON d.id = m.mail_domain_id ORDER BY m.address'
            ),
            'queue' => $queue,
            'mailLog' => $logResult->ok() ? trim($logResult->stdout) : '',
        ]);
    }

    public function queueFlush(Request $request, Response $response): Response
    {
        $result = $this->ctl->run('mail:queue:flush');
        $this->flash($result->ok() ? 'success' : 'error', $result->output());
        $this->db->audit($this->userId(), 'mail.queue.flush', '', $this->ip($request));
        return $this->redirect($response, '/mail');
    }

    public function queueDelete(Request $request, Response $response): Response
    {
        $id = strtoupper($this->input($request, 'queue_id'));
        if (!preg_match('/^[A-F0-9]{6,20}$/', $id)) {
            $this->flash('error', 'Invalid queue ID.');
            return $this->redirect($response, '/mail');
        }
        $result = $this->ctl->run('mail:queue:delete', ['id' => $id]);
        $this->flash($result->ok() ? 'success' : 'error', $result->output());
        $this->db->audit($this->userId(), 'mail.queue.delete', $id, $this->ip($request));
        return $this->redirect($response, '/mail');
    }

    public function addDomain(Request $request, Response $response): Response
    {
        $domain = strtolower($this->input($request, 'domain'));
        if (!Validator::domain($domain)) {
            $this->flash('error', 'Invalid domain name.');
            return $this->redirect($response, '/mail');
        }
        if ($this->db->one('SELECT id FROM mail_domains WHERE domain = ?', [$domain]) !== null) {
            $this->flash('error', 'Mail domain already exists.');
            return $this->redirect($response, '/mail');
        }

        $result = $this->ctl->run('mail:domain:add', ['domain' => $domain]);
        if (!$result->ok()) {
            $this->flash('error', 'mail:domain:add failed: ' . $result->output());
            return $this->redirect($response, '/mail');
        }

        // panelctl prints the DKIM DNS TXT record on stdout — keep it for display.
        $this->db->run(
            'INSERT INTO mail_domains (domain, dkim_selector, dkim_dns, created_at) VALUES (?, ?, ?, ?)',
            [$domain, 'mail', trim($result->stdout), time()]
        );
        $this->db->audit($this->userId(), 'mail.domain.add', $domain, $this->ip($request));
        $this->flash('success', "Mail domain {$domain} added. Set the DNS records shown below.");
        return $this->redirect($response, '/mail');
    }

    public function deleteDomain(Request $request, Response $response, array $args): Response
    {
        $row = $this->db->one('SELECT * FROM mail_domains WHERE id = ?', [(int) $args['id']]);
        if ($row === null) {
            return $this->redirect($response, '/mail');
        }
        if ($this->input($request, 'confirm_domain') !== $row['domain']) {
            $this->flash('error', 'Confirmation domain did not match — not deleted.');
            return $this->redirect($response, '/mail');
        }

        $result = $this->ctl->run('mail:domain:delete', ['domain' => (string) $row['domain']]);
        if (!$result->ok()) {
            $this->flash('error', 'mail:domain:delete failed: ' . $result->output());
            return $this->redirect($response, '/mail');
        }

        $this->db->run('DELETE FROM mail_domains WHERE id = ?', [$row['id']]);
        $this->db->audit($this->userId(), 'mail.domain.delete', (string) $row['domain'], $this->ip($request));
        $this->flash('success', "Mail domain {$row['domain']} and its mailboxes deleted.");
        return $this->redirect($response, '/mail');
    }

    public function addMailbox(Request $request, Response $response): Response
    {
        $address = strtolower($this->input($request, 'address'));
        $password = $this->input($request, 'password');

        if (!Validator::email($address)) {
            $this->flash('error', 'Invalid email address.');
            return $this->redirect($response, '/mail');
        }
        $domainPart = substr((string) strrchr($address, '@'), 1);
        $domain = $this->db->one('SELECT * FROM mail_domains WHERE domain = ?', [$domainPart]);
        if ($domain === null) {
            $this->flash('error', "Add {$domainPart} as a mail domain first.");
            return $this->redirect($response, '/mail');
        }
        if ($this->db->one('SELECT id FROM mailboxes WHERE address = ?', [$address]) !== null) {
            $this->flash('error', 'Mailbox already exists.');
            return $this->redirect($response, '/mail');
        }

        $generated = false;
        if ($password === '') {
            $password = Validator::randomPassword(16);
            $generated = true;
        } elseif (strlen($password) < 10) {
            $this->flash('error', 'Mailbox password must be at least 10 characters.');
            return $this->redirect($response, '/mail');
        }

        $result = $this->ctl->run('mail:mailbox:add', ['address' => $address], $password . "\n");
        if (!$result->ok()) {
            $this->flash('error', 'mail:mailbox:add failed: ' . $result->output());
            return $this->redirect($response, '/mail');
        }

        $this->db->run(
            'INSERT INTO mailboxes (mail_domain_id, address, created_at) VALUES (?, ?, ?)',
            [$domain['id'], $address, time()]
        );
        $this->db->audit($this->userId(), 'mail.mailbox.add', $address, $this->ip($request));
        $this->flash(
            'success',
            "Mailbox {$address} created."
            . ($generated ? " Password: {$password} — save it now, it will not be shown again." : '')
        );
        return $this->redirect($response, '/mail');
    }

    public function deleteMailbox(Request $request, Response $response, array $args): Response
    {
        $row = $this->db->one('SELECT * FROM mailboxes WHERE id = ?', [(int) $args['id']]);
        if ($row === null) {
            return $this->redirect($response, '/mail');
        }

        $result = $this->ctl->run('mail:mailbox:delete', ['address' => (string) $row['address']]);
        if (!$result->ok()) {
            $this->flash('error', 'mail:mailbox:delete failed: ' . $result->output());
            return $this->redirect($response, '/mail');
        }

        $this->db->run('DELETE FROM mailboxes WHERE id = ?', [$row['id']]);
        $this->db->audit($this->userId(), 'mail.mailbox.delete', (string) $row['address'], $this->ip($request));
        $this->flash('success', "Mailbox {$row['address']} deleted.");
        return $this->redirect($response, '/mail');
    }
}
