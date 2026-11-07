<?php
declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response as SlimResponse;

/**
 * Deja pasar solo al personal de GalviFintech (rol 'staff').
 * Va despues de AuthMiddleware, que es quien agrega el atributo 'rol'.
 */
final class SoloStaffMiddleware implements MiddlewareInterface
{
    public function process(Request $request, Handler $handler): Response
    {
        if ($request->getAttribute('rol') !== 'staff') {
            $res = new SlimResponse();
            $res->getBody()->write(json_encode(['error' => 'Acceso solo para el personal de GalviFintech']));
            return $res->withHeader('Content-Type', 'application/json; charset=utf-8')
                       ->withStatus(403);
        }
        return $handler->handle($request);
    }
}
