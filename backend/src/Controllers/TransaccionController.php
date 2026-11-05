<?php
declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * UC02 - Alta, consulta, modificacion y baja de transacciones.
 * Toda consulta filtra por usuario_id: cada cliente solo ve sus propios datos.
 */
final class TransaccionController extends BaseController
{
    private const TIPOS  = ['ingreso', 'gasto'];
    private const MEDIOS = ['efectivo', 'transferencia', 'debito', 'credito', 'otro'];
    private const MONTO_MAXIMO = 9999999999.99; // limite de DECIMAL(12,2)

    /** GET /api/transacciones?desde=&hasta=&tipo=&categoria= */
    public function listar(Request $req, Response $res): Response
    {
        [$where, $params, $errores] = $this->filtros($req);
        if ($errores !== []) {
            return $this->error($res, 'Filtros invalidos', 422, $errores);
        }

        $stmt = $this->db->prepare(
            "SELECT id, tipo, categoria, descripcion, monto, fecha, medio_pago, comprobante
               FROM transacciones
              WHERE $where
              ORDER BY fecha DESC, id DESC
              LIMIT 500"
        );
        $stmt->execute($params);

        $filas = array_map([$this, 'formatear'], $stmt->fetchAll());
        return $this->json($res, ['total' => count($filas), 'datos' => $filas]);
    }

    /** POST /api/transacciones */
    public function crear(Request $req, Response $res): Response
    {
        [$datos, $errores] = $this->validar($this->body($req));
        if ($errores !== []) {
            return $this->error($res, 'Datos invalidos', 422, $errores);
        }

        $stmt = $this->db->prepare(
            'INSERT INTO transacciones
                    (usuario_id, tipo, categoria, descripcion, monto, fecha, medio_pago, comprobante)
             VALUES (:usuario_id, :tipo, :categoria, :descripcion, :monto, :fecha, :medio_pago, :comprobante)'
        );
        $stmt->execute($datos + ['usuario_id' => $this->usuarioId($req)]);

        $id = (int) $this->db->lastInsertId();
        return $this->json($res, $this->buscar($id, $this->usuarioId($req)), 201);
    }

    /** PUT /api/transacciones/{id} */
    public function actualizar(Request $req, Response $res, array $args): Response
    {
        $id  = (int) $args['id'];
        $uid = $this->usuarioId($req);

        if ($this->buscar($id, $uid) === null) {
            return $this->error($res, 'Transaccion no encontrada', 404);
        }

        [$datos, $errores] = $this->validar($this->body($req));
        if ($errores !== []) {
            return $this->error($res, 'Datos invalidos', 422, $errores);
        }

        $stmt = $this->db->prepare(
            'UPDATE transacciones
                SET tipo = :tipo, categoria = :categoria, descripcion = :descripcion,
                    monto = :monto, fecha = :fecha, medio_pago = :medio_pago,
                    comprobante = :comprobante
              WHERE id = :id AND usuario_id = :usuario_id'
        );
        $stmt->execute($datos + ['id' => $id, 'usuario_id' => $uid]);

        return $this->json($res, $this->buscar($id, $uid));
    }

    /** DELETE /api/transacciones/{id} */
    public function eliminar(Request $req, Response $res, array $args): Response
    {
        $stmt = $this->db->prepare(
            'DELETE FROM transacciones WHERE id = :id AND usuario_id = :usuario_id'
        );
        $stmt->execute(['id' => (int) $args['id'], 'usuario_id' => $this->usuarioId($req)]);

        if ($stmt->rowCount() === 0) {
            return $this->error($res, 'Transaccion no encontrada', 404);
        }
        return $res->withStatus(204);
    }

    // ------------------------------------------------------------------
    // Funciones auxiliares
    // ------------------------------------------------------------------

    /** Valida y normaliza el cuerpo de una transaccion. Devuelve [datos, errores]. */
    private function validar(array $d): array
    {
        $errores = [];

        $tipo = (string) ($d['tipo'] ?? '');
        if (!in_array($tipo, self::TIPOS, true)) {
            $errores['tipo'] = 'Debe ser "ingreso" o "gasto".';
        }

        $categoria = trim((string) ($d['categoria'] ?? ''));
        if ($categoria === '' || mb_strlen($categoria) > 50) {
            $errores['categoria'] = 'Obligatoria, maximo 50 caracteres.';
        }

        $descripcion = trim((string) ($d['descripcion'] ?? ''));
        if ($descripcion === '' || mb_strlen($descripcion) > 255) {
            $errores['descripcion'] = 'Obligatoria, maximo 255 caracteres.';
        }

        $monto = $d['monto'] ?? null;
        if (!is_numeric($monto) || (float) $monto <= 0 || (float) $monto > self::MONTO_MAXIMO) {
            $errores['monto'] = 'Debe ser un numero mayor a 0.';
        }

        $fecha = (string) ($d['fecha'] ?? '');
        if (!$this->esFechaValida($fecha)) {
            $errores['fecha'] = 'Formato AAAA-MM-DD.';
        }

        $medio = (string) ($d['medio_pago'] ?? 'efectivo');
        if (!in_array($medio, self::MEDIOS, true)) {
            $errores['medio_pago'] = 'Valor no permitido.';
        }

        $comprobante = trim((string) ($d['comprobante'] ?? ''));
        if (mb_strlen($comprobante) > 50) {
            $errores['comprobante'] = 'Maximo 50 caracteres.';
        }

        $datos = [
            'tipo'        => $tipo,
            'categoria'   => $categoria,
            'descripcion' => $descripcion,
            'monto'       => is_numeric($monto) ? round((float) $monto, 2) : 0,
            'fecha'       => $fecha,
            'medio_pago'  => $medio,
            'comprobante' => $comprobante !== '' ? $comprobante : null,
        ];
        return [$datos, $errores];
    }

    /** Arma el WHERE de los listados a partir de la query string. */
    private function filtros(Request $req): array
    {
        $q = $req->getQueryParams();
        $where   = ['usuario_id = :usuario_id'];
        $params  = ['usuario_id' => $this->usuarioId($req)];
        $errores = [];

        foreach (['desde' => '>=', 'hasta' => '<='] as $campo => $operador) {
            if (!empty($q[$campo])) {
                if (!$this->esFechaValida($q[$campo])) {
                    $errores[$campo] = 'Formato AAAA-MM-DD.';
                    continue;
                }
                $where[] = "fecha $operador :$campo";
                $params[$campo] = $q[$campo];
            }
        }
        if (!empty($q['tipo'])) {
            if (!in_array($q['tipo'], self::TIPOS, true)) {
                $errores['tipo'] = 'Debe ser "ingreso" o "gasto".';
            } else {
                $where[] = 'tipo = :tipo';
                $params['tipo'] = $q['tipo'];
            }
        }
        if (!empty($q['categoria'])) {
            $where[] = 'categoria = :categoria';
            $params['categoria'] = (string) $q['categoria'];
        }

        return [implode(' AND ', $where), $params, $errores];
    }

    private function buscar(int $id, int $usuarioId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, tipo, categoria, descripcion, monto, fecha, medio_pago, comprobante
               FROM transacciones WHERE id = :id AND usuario_id = :usuario_id'
        );
        $stmt->execute(['id' => $id, 'usuario_id' => $usuarioId]);
        $fila = $stmt->fetch();
        return $fila ? $this->formatear($fila) : null;
    }

    private function formatear(array $t): array
    {
        $t['id']    = (int) $t['id'];
        $t['monto'] = (float) $t['monto'];
        return $t;
    }
}
