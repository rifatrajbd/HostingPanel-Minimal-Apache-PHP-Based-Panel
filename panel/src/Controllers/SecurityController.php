<?php

declare(strict_types=1);

namespace Panel\Controllers;

use Panel\Auth\AuthService;
use Panel\Database;
use Panel\Support\Totp;
use Panel\Support\View;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class SecurityController extends Controller
{
    public function __construct(
        View $view,
        private readonly Database $db,
        private readonly AuthService $auth
    ) {
        parent::__construct($view);
    }

    public function index(Request $request, Response $response): Response
    {
        $user = $this->auth->user();
        $enrollSecret = $_SESSION['totp_enroll_secret'] ?? null;

        return $this->html($response, 'security/index', [
            'title' => 'Security',
            'active' => 'security',
            'user' => $user,
            'enrollSecret' => $enrollSecret,
            'enrollUri' => is_string($enrollSecret) && $user !== null
                ? Totp::uri($enrollSecret, (string) $user['username'], 'HostingPanel')
                : null,
            'auditLog' => $this->db->all(
                'SELECT action, details, ip, created_at FROM audit_log ORDER BY id DESC LIMIT 25'
            ),
        ]);
    }

    public function changePassword(Request $request, Response $response): Response
    {
        $current = $this->input($request, 'current_password');
        $new = $this->input($request, 'new_password');
        $confirm = $this->input($request, 'confirm_password');

        if (strlen($new) < 12) {
            $this->flash('error', 'New password must be at least 12 characters.');
        } elseif ($new !== $confirm) {
            $this->flash('error', 'New passwords do not match.');
        } elseif (!$this->auth->changePassword($this->userId(), $current, $new)) {
            $this->flash('error', 'Current password is incorrect.');
        } else {
            $this->db->audit($this->userId(), 'user.password_change', '', $this->ip($request));
            $this->flash('success', 'Password updated.');
        }
        return $this->redirect($response, '/security');
    }

    public function startTwoFactor(Request $request, Response $response): Response
    {
        $this->auth->startTotpEnrollment($this->userId());
        return $this->redirect($response, '/security');
    }

    public function confirmTwoFactor(Request $request, Response $response): Response
    {
        $code = $this->input($request, 'code');
        if ($this->auth->confirmTotpEnrollment($this->userId(), $code)) {
            $this->db->audit($this->userId(), 'user.2fa_enable', '', $this->ip($request));
            $this->flash('success', 'Two-factor authentication is now enabled.');
        } else {
            $this->flash('error', 'Code did not match. Scan the secret again and retry.');
        }
        return $this->redirect($response, '/security');
    }

    public function disableTwoFactor(Request $request, Response $response): Response
    {
        $password = $this->input($request, 'password');
        if ($this->auth->disableTotp($this->userId(), $password)) {
            $this->db->audit($this->userId(), 'user.2fa_disable', '', $this->ip($request));
            $this->flash('success', 'Two-factor authentication disabled.');
        } else {
            $this->flash('error', 'Password incorrect — 2FA unchanged.');
        }
        return $this->redirect($response, '/security');
    }
}
