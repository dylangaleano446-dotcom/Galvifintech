<?php
declare(strict_types=1);

namespace App\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response as SlimResponse;
use Throwable;

/**
 * Protege las rutas privadas: exige un token JWT valido en la cabecera
 * Authorization: Bearer <token> y agrega 'usuario_id' a la peticion.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private array $jwtConfig)
    {
    }

    public function process(Request $request, Handler $handler): Response
    {
        $cabecera = $request->getHeaderLine('Authorization');

        if (!preg_match('/^Bearer\s+(\S+)$/i', $cabecera, $m)) {
            return $this->noAutorizado('Falta el token de acceso');
        }

        try {
            $datos = JWT::decode($m[1], new Key($this->jwtConfig['secret'], 'HS256'));
        } catch (Throwable) {
            // Firma invalida, token vencido o mal formado
            return $this->noAutorizado('Token invalido o vencido');
        }

        if (($datos->iss ?? null) !== $this->jwtConfig['issuer']) {
            return $this->noAutorizado('Token invalido o vencido');
        }

        $request = $request->withAttribute('usuario_id', (int) $datos->sub)
                           ->withAttribute('rol', $datos->rol ?? 'operador');

        return $handler->handle($request);
    }

    private function noAutorizado(string $mensaje): Response
    {
        $res = new SlimResponse();
        $res->getBody()->write(json_encode(['error' => $mensaje], JSON_UNESCAPED_UNICODE));
        return $res->withHeader('Content-Type', 'application/json; charset=utf-8')
                   ->withStatus(401);
    }
}
