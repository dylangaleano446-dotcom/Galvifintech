<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Servicios\Abono;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use Slim\Psr7\Response as SlimResponse;
use Throwable;

/**
 * Protege las rutas privadas: exige un token JWT valido en la cabecera
 * Authorization: Bearer <token> y que el abono del cliente este al dia.
 * Agrega 'usuario_id' y 'rol' a la peticion.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private array $jwtConfig, private PDO $db)
    {
    }

    public function process(Request $request, Handler $handler): Response
    {
        $cabecera = $request->getHeaderLine('Authorization');

        if (!preg_match('/^Bearer\s+(\S+)$/i', $cabecera, $m)) {
            return $this->rechazar(401, 'Falta el token de acceso');
        }

        try {
            $datos = JWT::decode($m[1], new Key($this->jwtConfig['secret'], 'HS256'));
        } catch (Throwable) {
            // Firma invalida, token vencido o mal formado
            return $this->rechazar(401, 'Token invalido o vencido');
        }

        if (($datos->iss ?? null) !== $this->jwtConfig['issuer']) {
            return $this->rechazar(401, 'Token invalido o vencido');
        }

        // Se consulta la base en cada pedido: si el abono vence con la sesion abierta, se corta en el momento
        $stmt = $this->db->prepare('SELECT rol, abono_hasta FROM usuarios WHERE id = :id');
        $stmt->execute(['id' => (int) $datos->sub]);
        $u = $stmt->fetch();
        if (!$u) {
            return $this->rechazar(401, 'Token invalido o vencido');
        }
        if (Abono::bloqueada($u['rol'], $u['abono_hasta'])) {
            return $this->rechazar(403, Abono::mensajeBloqueo($u['abono_hasta']), 'cuenta_suspendida');
        }

        $request = $request->withAttribute('usuario_id', (int) $datos->sub)
                           ->withAttribute('rol', $u['rol']);

        return $handler->handle($request);
    }

    private function rechazar(int $status, string $mensaje, ?string $codigo = null): Response
    {
        $cuerpo = ['error' => $mensaje] + ($codigo !== null ? ['codigo' => $codigo] : []);
        $res = new SlimResponse();
        $res->getBody()->write(json_encode($cuerpo, JSON_UNESCAPED_UNICODE));
        return $res->withHeader('Content-Type', 'application/json; charset=utf-8')
                   ->withStatus($status);
    }
}
