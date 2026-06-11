<?php

declare(strict_types=1);

namespace Panel\Middleware;

use Panel\Support\Csrf;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

final class CsrfMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $body = $request->getParsedBody();
            $token = is_array($body) ? ($body['_csrf'] ?? null) : null;
            if (!Csrf::validate(is_string($token) ? $token : null)) {
                $response = new Response(403);
                $response->getBody()->write(
                    '<h1>403 Forbidden</h1><p>Invalid or missing CSRF token. Go back and try again.</p>'
                );
                return $response->withHeader('Content-Type', 'text/html');
            }
        }

        return $handler->handle($request);
    }
}
