<?php
declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * UC03 - Datos consolidados para el Dashboard (balance, graficos y alertas).
 */
final class DashboardController extends BaseController
{
    /** GET /api/dashboard/resumen?anio=2026&mes=10 */
    public function resumen(Request $req, Response $res): Response
    {
        $q    = $req->getQueryParams();
        $anio = (int) ($q['anio'] ?? date('Y'));
        $mes  = (int) ($q['mes'] ?? date('n'));
        if ($anio < 2000 || $anio > 2100 || $mes < 1 || $mes > 12) {
            return $this->error($res, 'Periodo invalido', 422);
        }

        $uid   = $this->usuarioId($req);
        $desde = sprintf('%04d-%02d-01', $anio, $mes);
        $hasta = date('Y-m-t', strtotime($desde)); // ultimo dia del mes

        // 1) Totales del mes por tipo
        $stmt = $this->db->prepare(
            'SELECT tipo, SUM(monto) AS total
               FROM transacciones
              WHERE usuario_id = :u AND fecha BETWEEN :d AND :h
              GROUP BY tipo'
        );
        $stmt->execute(['u' => $uid, 'd' => $desde, 'h' => $hasta]);
        $totales = ['ingreso' => 0.0, 'gasto' => 0.0];
        foreach ($stmt->fetchAll() as $f) {
            $totales[$f['tipo']] = (float) $f['total'];
        }

        // 2) Gastos del mes agrupados por categoria (grafico de torta)
        $stmt = $this->db->prepare(
            "SELECT categoria, SUM(monto) AS total
               FROM transacciones
              WHERE usuario_id = :u AND tipo = 'gasto' AND fecha BETWEEN :d AND :h
              GROUP BY categoria
              ORDER BY total DESC"
        );
        $stmt->execute(['u' => $uid, 'd' => $desde, 'h' => $hasta]);
        $porCategoria = array_map(
            fn ($f) => ['categoria' => $f['categoria'], 'total' => (float) $f['total']],
            $stmt->fetchAll()
        );

        // 3) Evolucion de los ultimos 6 meses (grafico de barras)
        $inicio6 = date('Y-m-01', strtotime("$desde -5 months"));
        $stmt = $this->db->prepare(
            'SELECT tipo, fecha, SUM(monto) AS total
               FROM transacciones
              WHERE usuario_id = :u AND fecha BETWEEN :d AND :h
              GROUP BY tipo, fecha'
        );
        $stmt->execute(['u' => $uid, 'd' => $inicio6, 'h' => $hasta]);
        $evolucion = [];
        for ($i = 5; $i >= 0; $i--) {
            $clave = date('Y-m', strtotime("$desde -$i months"));
            $evolucion[$clave] = ['periodo' => $clave, 'ingresos' => 0.0, 'gastos' => 0.0];
        }
        foreach ($stmt->fetchAll() as $f) {
            $clave = substr($f['fecha'], 0, 7);
            $campo = $f['tipo'] === 'ingreso' ? 'ingresos' : 'gastos';
            $evolucion[$clave][$campo] += (float) $f['total'];
        }

        // 4) Presupuesto vs gasto real del mes (alertas)
        $stmt = $this->db->prepare(
            "SELECT p.categoria, p.monto_limite, COALESCE(SUM(t.monto), 0) AS gastado
               FROM presupuestos p
               LEFT JOIN transacciones t
                      ON t.usuario_id = p.usuario_id
                     AND t.categoria  = p.categoria
                     AND t.tipo       = 'gasto'
                     AND t.fecha BETWEEN :d AND :h
              WHERE p.usuario_id = :u AND p.anio = :a AND p.mes = :m
              GROUP BY p.id, p.categoria, p.monto_limite
              ORDER BY p.categoria"
        );
        $stmt->execute(['u' => $uid, 'd' => $desde, 'h' => $hasta, 'a' => $anio, 'm' => $mes]);
        $presupuestos = array_map(function ($f) {
            $limite = (float) $f['monto_limite'];
            $gastado = (float) $f['gastado'];
            $porcentaje = $limite > 0 ? round($gastado / $limite * 100, 1) : 0;
            return [
                'categoria'  => $f['categoria'],
                'limite'     => $limite,
                'gastado'    => $gastado,
                'porcentaje' => $porcentaje,
                'estado'     => $porcentaje > 100 ? 'excedido' : ($porcentaje >= 80 ? 'alerta' : 'ok'),
            ];
        }, $stmt->fetchAll());

        // 5) Ultimos 5 movimientos
        $stmt = $this->db->prepare(
            'SELECT id, tipo, categoria, descripcion, monto, fecha
               FROM transacciones WHERE usuario_id = :u
              ORDER BY fecha DESC, id DESC LIMIT 5'
        );
        $stmt->execute(['u' => $uid]);
        $ultimos = array_map(function ($t) {
            $t['id'] = (int) $t['id'];
            $t['monto'] = (float) $t['monto'];
            return $t;
        }, $stmt->fetchAll());

        return $this->json($res, [
            'periodo'          => ['anio' => $anio, 'mes' => $mes, 'desde' => $desde, 'hasta' => $hasta],
            'ingresos'         => $totales['ingreso'],
            'gastos'           => $totales['gasto'],
            'balance'          => round($totales['ingreso'] - $totales['gasto'], 2),
            'gastos_categoria' => $porCategoria,
            'evolucion'        => array_values($evolucion),
            'presupuestos'     => $presupuestos,
            'ultimos'          => $ultimos,
        ]);
    }
}
