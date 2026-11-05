<?php
declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Presupuestos mensuales por categoria (limite de gasto).
 */
final class PresupuestoController extends BaseController
{
    /** GET /api/presupuestos?anio=&mes= */
    public function listar(Request $req, Response $res): Response
    {
        $q = $req->getQueryParams();
        $stmt = $this->db->prepare(
            'SELECT id, categoria, monto_limite, anio, mes
               FROM presupuestos
              WHERE usuario_id = :u AND anio = :a AND mes = :m
              ORDER BY categoria'
        );
        $stmt->execute([
            'u' => $this->usuarioId($req),
            'a' => (int) ($q['anio'] ?? date('Y')),
            'm' => (int) ($q['mes'] ?? date('n')),
        ]);
        $filas = array_map(fn ($p) => [
            'id'           => (int) $p['id'],
            'categoria'    => $p['categoria'],
            'monto_limite' => (float) $p['monto_limite'],
            'anio'         => (int) $p['anio'],
            'mes'          => (int) $p['mes'],
        ], $stmt->fetchAll());

        return $this->json($res, $filas);
    }

    /** POST /api/presupuestos  (crea o actualiza el limite de una categoria en un mes) */
    public function guardar(Request $req, Response $res): Response
    {
        $d = $this->body($req);
        $categoria = trim((string) ($d['categoria'] ?? ''));
        $limite    = $d['monto_limite'] ?? null;
        $anio      = (int) ($d['anio'] ?? 0);
        $mes       = (int) ($d['mes'] ?? 0);

        $errores = [];
        if ($categoria === '' || mb_strlen($categoria) > 50) {
            $errores['categoria'] = 'Obligatoria, maximo 50 caracteres.';
        }
        if (!is_numeric($limite) || (float) $limite <= 0) {
            $errores['monto_limite'] = 'Debe ser un numero mayor a 0.';
        }
        if ($anio < 2000 || $anio > 2100) {
            $errores['anio'] = 'Anio invalido.';
        }
        if ($mes < 1 || $mes > 12) {
            $errores['mes'] = 'Debe estar entre 1 y 12.';
        }
        if ($errores !== []) {
            return $this->error($res, 'Datos invalidos', 422, $errores);
        }

        $uid = $this->usuarioId($req);
        $clave = ['u' => $uid, 'c' => $categoria, 'a' => $anio, 'm' => $mes];

        $buscar = $this->db->prepare(
            'SELECT id FROM presupuestos WHERE usuario_id = :u AND categoria = :c AND anio = :a AND mes = :m'
        );
        $buscar->execute($clave);
        $existente = $buscar->fetch();

        if ($existente) {
            $stmt = $this->db->prepare('UPDATE presupuestos SET monto_limite = :l WHERE id = :id');
            $stmt->execute(['l' => round((float) $limite, 2), 'id' => $existente['id']]);
            $id = (int) $existente['id'];
            $status = 200;
        } else {
            $stmt = $this->db->prepare(
                'INSERT INTO presupuestos (usuario_id, categoria, monto_limite, anio, mes)
                 VALUES (:u, :c, :l, :a, :m)'
            );
            $stmt->execute($clave + ['l' => round((float) $limite, 2)]);
            $id = (int) $this->db->lastInsertId();
            $status = 201;
        }

        return $this->json($res, [
            'id'           => $id,
            'categoria'    => $categoria,
            'monto_limite' => round((float) $limite, 2),
            'anio'         => $anio,
            'mes'          => $mes,
        ], $status);
    }
}
