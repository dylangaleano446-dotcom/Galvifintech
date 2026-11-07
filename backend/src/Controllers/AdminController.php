<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Servicios\Abono;
use App\Servicios\ValidadorUsuario;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

/**
 * Panel del personal de GalviFintech: clientes, estado del abono y registro de pagos.
 * Todas las rutas pasan por AuthMiddleware y SoloStaffMiddleware.
 */
final class AdminController extends BaseController
{
    private const MEDIOS = ['transferencia', 'mercadopago', 'efectivo', 'otro'];

    /** GET /api/admin/clientes */
    public function clientes(Request $req, Response $res): Response
    {
        $filas = $this->db->query(
            "SELECT u.id, u.nombre, u.email, u.empresa, u.cuit, u.plan, u.abono_hasta, u.creado_en,
                    (SELECT MAX(p.creado_en) FROM pagos p WHERE p.usuario_id = u.id) AS ultimo_pago
               FROM usuarios u
              WHERE u.rol = 'cliente'
              ORDER BY u.empresa"
        )->fetchAll();

        $resumen = ['activo' => 0, 'por_vencer' => 0, 'suspendido' => 0, 'sin_abono' => 0];
        $clientes = array_map(function (array $c) use (&$resumen) {
            $c['id'] = (int) $c['id'];
            $c += Abono::estado('cliente', $c['abono_hasta']);
            $resumen[$c['estado']]++;
            return $c;
        }, $filas);

        return $this->json($res, ['resumen' => $resumen, 'clientes' => $clientes]);
    }

    /** POST /api/admin/clientes  (alta de un cliente nuevo; queda pendiente hasta el primer pago) */
    public function crearCliente(Request $req, Response $res): Response
    {
        $body = $this->body($req);
        [$d, $errores] = ValidadorUsuario::validar($body);
        $plan = (string) ($body['plan'] ?? '');
        if (!isset(Abono::PLANES[$plan])) {
            $errores['plan'] = 'Debe ser basico, pyme o empresa.';
        }
        if ($errores !== []) {
            return $this->error($res, 'Datos invalidos', 422, $errores);
        }

        $existe = $this->db->prepare('SELECT id FROM usuarios WHERE email = :email');
        $existe->execute(['email' => $d['email']]);
        if ($existe->fetch()) {
            return $this->error($res, 'El email ya esta registrado', 409);
        }

        $stmt = $this->db->prepare(
            "INSERT INTO usuarios (nombre, email, password_hash, empresa, cuit, rol, plan)
             VALUES (:nombre, :email, :hash, :empresa, :cuit, 'cliente', :plan)"
        );
        $stmt->execute([
            'nombre'  => $d['nombre'],
            'email'   => $d['email'],
            'hash'    => password_hash($d['password'], PASSWORD_DEFAULT),
            'empresa' => $d['empresa'],
            'cuit'    => $d['cuit'] !== '' ? $d['cuit'] : null,
            'plan'    => $plan,
        ]);

        return $this->json($res, [
            'id'      => (int) $this->db->lastInsertId(),
            'mensaje' => 'Cliente creado. Registre el primer pago para habilitar la cuenta.',
        ], 201);
    }

    /** POST /api/admin/pagos  (registra un pago y extiende el abono) */
    public function registrarPago(Request $req, Response $res): Response
    {
        $d = $this->body($req);
        $usuarioId   = (int) ($d['usuario_id'] ?? 0);
        $meses       = (int) ($d['meses'] ?? 0);
        $medio       = (string) ($d['medio_pago'] ?? 'transferencia');
        $comprobante = trim((string) ($d['comprobante'] ?? ''));

        $buscar = $this->db->prepare("SELECT id, empresa, plan, abono_hasta FROM usuarios WHERE id = :id AND rol = 'cliente'");
        $buscar->execute(['id' => $usuarioId]);
        $cliente = $buscar->fetch();
        if (!$cliente) {
            return $this->error($res, 'Cliente no encontrado', 404);
        }

        $plan = (string) ($d['plan'] ?? $cliente['plan'] ?? '');
        $errores = [];
        if (!isset(Abono::PLANES[$plan])) {
            $errores['plan'] = 'Debe ser basico, pyme o empresa.';
        }
        if (!isset(Abono::DESCUENTOS[$meses])) {
            $errores['meses'] = 'Debe ser 1, 6 o 12.';
        }
        if (!in_array($medio, self::MEDIOS, true)) {
            $errores['medio_pago'] = 'Valor no permitido.';
        }
        if (mb_strlen($comprobante) > 50) {
            $errores['comprobante'] = 'Maximo 50 caracteres.';
        }
        $monto = $d['monto_usd'] ?? null;
        if ($monto === null || $monto === '') {
            $monto = $errores === [] ? Abono::montoSugerido($plan, $meses) : 0;
        } elseif (!is_numeric($monto) || (float) $monto <= 0 || (float) $monto > 99999999) {
            $errores['monto_usd'] = 'Debe ser un numero mayor a 0.';
        }
        if ($errores !== []) {
            return $this->error($res, 'Datos invalidos', 422, $errores);
        }

        [$desde, $hasta] = Abono::nuevoPeriodo($cliente['abono_hasta'], $meses);

        // El pago y la nueva fecha se guardan juntos: o se graban los dos o ninguno
        $this->db->beginTransaction();
        try {
            $ins = $this->db->prepare(
                'INSERT INTO pagos (usuario_id, plan, meses, monto_usd, medio_pago, comprobante,
                                    periodo_desde, periodo_hasta, registrado_por)
                 VALUES (:u, :plan, :meses, :monto, :medio, :comp, :desde, :hasta, :staff)'
            );
            $ins->execute([
                'u' => $usuarioId, 'plan' => $plan, 'meses' => $meses, 'monto' => round((float) $monto, 2),
                'medio' => $medio, 'comp' => $comprobante !== '' ? $comprobante : null,
                'desde' => $desde, 'hasta' => $hasta, 'staff' => $this->usuarioId($req),
            ]);
            $upd = $this->db->prepare('UPDATE usuarios SET plan = :plan, abono_hasta = :hasta WHERE id = :id');
            $upd->execute(['plan' => $plan, 'hasta' => $hasta, 'id' => $usuarioId]);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $this->json($res, [
            'mensaje'       => "Pago registrado. {$cliente['empresa']} queda habilitado hasta el "
                               . date('d/m/Y', strtotime($hasta)) . '.',
            'usuario_id'    => $usuarioId,
            'plan'          => $plan,
            'meses'         => $meses,
            'monto_usd'     => round((float) $monto, 2),
            'periodo_desde' => $desde,
            'periodo_hasta' => $hasta,
        ] + Abono::estado('cliente', $hasta), 201);
    }

    /** GET /api/admin/pagos?usuario_id=  (ultimos 50 pagos) */
    public function pagos(Request $req, Response $res): Response
    {
        $q = $req->getQueryParams();
        $sql = 'SELECT p.id, p.usuario_id, u.empresa, p.plan, p.meses, p.monto_usd, p.medio_pago,
                       p.comprobante, p.periodo_desde, p.periodo_hasta, p.creado_en
                  FROM pagos p JOIN usuarios u ON u.id = p.usuario_id';
        $params = [];
        if (!empty($q['usuario_id'])) {
            $sql .= ' WHERE p.usuario_id = :u';
            $params['u'] = (int) $q['usuario_id'];
        }
        $stmt = $this->db->prepare($sql . ' ORDER BY p.creado_en DESC, p.id DESC LIMIT 50');
        $stmt->execute($params);

        $filas = array_map(function (array $p) {
            $p['id'] = (int) $p['id'];
            $p['usuario_id'] = (int) $p['usuario_id'];
            $p['meses'] = (int) $p['meses'];
            $p['monto_usd'] = (float) $p['monto_usd'];
            return $p;
        }, $stmt->fetchAll());

        return $this->json($res, $filas);
    }
}
