<?php
declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;
use App\ApiResponse;
use App\Helpers\Jwt;
use App\AppLogger;

class AuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = strtoupper($request->getMethod());
        if ($method === 'OPTIONS') {
            return $handler->handle($request);
        }

        $path = $request->getUri()->getPath();
        // Allow login route without token (handles apps mounted under a base path)
        $pathNormalized = rtrim($path, '/');
        if (preg_match('#/login$#i', $pathNormalized)) {
            return $handler->handle($request);
        }

        $authHeader = $request->getHeaderLine('Authorization');
        if (empty($authHeader)) {
            AppLogger::warning('Unknown', 'Authorization header missing', ['path' => $path]);
            $resp = new Response();
            return ApiResponse::error($resp, 'Authorization Bearer token required', null, 401);
        }

        if (!preg_match('/^Bearer\s+(.*)$/i', $authHeader, $matches)) {
            AppLogger::warning('Unknown', 'Authorization header malformed', ['header' => $authHeader]);
            $resp = new Response();
            return ApiResponse::error($resp, 'Authorization header malformed', null, 401);
        }

        $token = $matches[1];
        $secret = Jwt::getSecret();

        try {
            $payload = Jwt::decode($token, $secret);
            // attach payload to request for downstream handlers
            $request = $request->withAttribute('jwt', $payload);
            return $handler->handle($request);
        } catch (\Throwable $e) {
            AppLogger::warning('Unknown', 'JWT validation failed', ['error' => $e->getMessage(), 'path' => $path]);
            $resp = new Response();
            return ApiResponse::error($resp, 'Invalid or expired token', null, 401);
        }
    }
}
