<?php

declare(strict_types=1);

namespace Panel\Controllers;

use Panel\Auth\AuthService;
use Panel\Support\View;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AuthController extends Controller
{
    public function __construct(View $view, private readonly AuthService $auth)
    {
        parent::__construct($view);
    }

    public function showLogin(Request $request, Response $response): Response
    {
        if (!empty($_SESSION['user_id'])) {
            return $this->redirect($response, '/');
        }
        return $this->html($response, 'login', ['title' => 'Sign in'], null);
    }

    public function login(Request $request, Response $response): Response
    {
        $username = $this->input($request, 'username');
        $password = $this->input($request, 'password');

        $result = $this->auth->attempt($username, $password, $this->ip($request));

        return match ($result) {
            'ok' => $this->redirect($response, '/'),
            'totp' => $this->redirect($response, '/login/2fa'),
            'throttled' => $this->loginError($response, 'Too many failed attempts. Try again in 15 minutes.'),
            default => $this->loginError($response, 'Invalid username or password.'),
        };
    }

    public function showTwoFactor(Request $request, Response $response): Response
    {
        if (empty($_SESSION['pending_user_id'])) {
            return $this->redirect($response, '/login');
        }
        return $this->html($response, 'login-2fa', ['title' => 'Two-factor authentication'], null);
    }

    public function twoFactor(Request $request, Response $response): Response
    {
        if (empty($_SESSION['pending_user_id'])) {
            return $this->redirect($response, '/login');
        }
        $code = $this->input($request, 'code');
        if ($this->auth->completeTotp($code, $this->ip($request))) {
            return $this->redirect($response, '/');
        }
        $this->flash('error', 'Invalid code. Try again.');
        return $this->redirect($response, '/login/2fa');
    }

    public function logout(Request $request, Response $response): Response
    {
        $this->auth->logout();
        return $this->redirect($response, '/login');
    }

    private function loginError(Response $response, string $message): Response
    {
        return $this->html($response, 'login', ['title' => 'Sign in', 'error' => $message], null);
    }
}
