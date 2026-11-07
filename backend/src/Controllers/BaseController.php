<?php
declare(strict_types=1);

namespace App\Controllers;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Funciones comunes a todos los controladores.
 */
abstract class BaseController
{
    public function __construct(protected PDO $db)
    {
    }

    /** Devuelve una respuesta JSON con el codigo HTTP indicado. */
    protected function json(Response $res, mixed $data, int $status = 200): Response
    {
        $res->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
        return $res->withHeader('Content-Type', 'application/json; charset=utf-8')
                   ->withStatus($status);
    }

    /** Respuesta de error estandar: { "error": "...", "codigo": "...", "detalles": {...} } */
    protected function error(Response $res, string $mensaje, int $status, array $detalles = [], ?string $codigo = null): Response
    {
        $cuerpo = ['error' => $mensaje];
        if ($codigo !== null) {
            $cuerpo['codigo'] = $codigo; // permite al frontend reconocer casos especiales
        }
        if ($detalles !== []) {
            $cuerpo['detalles'] = $detalles;
        }
        return $this->json($res, $cuerpo, $status);
    }

    /** Cuerpo JSON de la peticion como array (vacio si no hay cuerpo). */
    protected function body(Request $req): array
    {
        $datos = $req->getParsedBody();
        return is_array($datos) ? $datos : [];
    }

    /** Id del usuario autenticado (lo agrega AuthMiddleware). */
    protected function usuarioId(Request $req): int
    {
        return (int) $req->getAttribute('usuario_id');
    }

    /** Valida una fecha con formato AAAA-MM-DD. */
    protected function esFechaValida(?string $fecha): bool
    {
        if ($fecha === null || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return false;
        }
        [$a, $m, $d] = array_map('intval', explode('-', $fecha));
        return checkdate($m, $d, $a);
    }
}
