<?php
declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response as SlimResponse;

/**
 * Permite que el frontend (otro origen) consuma la API y agrega
 * cabeceras de seguridad basicas a todas las respuestas.
 */
final class CorsMiddleware implements MiddlewareInterface
{
    public function __construct(private string $origenPermitido)
    {
    }

    public function process(Request $request, Handler $handler): Response
    {
        // Las peticiones de verificacion previa (OPTIONS) se responden sin pasar por las rutas
        $response = $request->getMethod() === 'OPTIONS'
            ? new SlimResponse(204)
            : $handler->handle($request);

        return $response
            ->withHeader('Access-Control-Allow-Origin', $this->origenPermitido)
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Vary', 'Origin')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('Referrer-Policy', 'no-referrer');
    }
}
