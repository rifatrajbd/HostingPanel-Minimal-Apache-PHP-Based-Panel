<?php

declare(strict_types=1);

namespace Panel\Controllers;

use Panel\Support\View;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

abstract class Controller
{
    public function __construct(protected readonly View $view)
    {
    }

    /** @param array<string, mixed> $data */
    protected function html(Response $response, string $template, array $data = [], ?string $layout = 'layout'): Response
    {
        $data['flash'] = $this->takeFlash();
        $response->getBody()->write($this->view->render($template, $data, $layout));
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    protected function redirect(Response $response, string $path): Response
    {
        return $response->withStatus(302)->withHeader('Location', $path);
    }

    protected function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /** @return list<array{type: string, message: string}> */
    private function takeFlash(): array
    {
        $flash = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $flash;
    }

    protected function input(Request $request, string $key, string $default = ''): string
    {
        $body = $request->getParsedBody();
        $value = is_array($body) ? ($body[$key] ?? $default) : $default;
        return is_string($value) ? trim($value) : $default;
    }

    protected function ip(Request $request): string
    {
        return (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown');
    }

    protected function userId(): int
    {
        return (int) ($_SESSION['user_id'] ?? 0);
    }
}
