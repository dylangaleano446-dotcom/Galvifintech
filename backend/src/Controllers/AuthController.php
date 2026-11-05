<?php
declare(strict_types=1);

namespace App\Controllers;

use Firebase\JWT\JWT;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * UC01 - Registro, inicio de sesion y datos del usuario autenticado.
 */
final class AuthController extends BaseController
{
    private const MAX_INTENTOS     = 5;   // intentos fallidos antes de bloquear
    private const MINUTOS_BLOQUEO  = 15;  // duracion del bloqueo

    public function __construct(PDO $db, private array $jwtConfig)
    {
        parent::__construct($db);
    }

    /** POST /api/auth/register */
    public function registrar(Request $req, Response $res): Response
    {
        $d = $this->body($req);
        $nombre   = trim((string) ($d['nombre'] ?? ''));
        $email    = strtolower(trim((string) ($d['email'] ?? '')));
        $password = (string) ($d['password'] ?? '');
        $empresa  = trim((string) ($d['empresa'] ?? ''));
        $cuit     = preg_replace('/\D/', '', (string) ($d['cuit'] ?? ''));

        $errores = [];
        if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 100) {
            $errores['nombre'] = 'Debe tener entre 3 y 100 caracteres.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
            $errores['email'] = 'Email invalido.';
        }
        if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            $errores['password'] = 'Minimo 8 caracteres, con al menos una letra y un numero.';
        }
        if (mb_strlen($empresa) < 2 || mb_strlen($empresa) > 150) {
            $errores['empresa'] = 'Debe tener entre 2 y 150 caracteres.';
        }
        if ($cuit !== '' && strlen($cuit) !== 11) {
            $errores['cuit'] = 'El CUIT debe tener 11 digitos.';
        }
        if ($errores !== []) {
            return $this->error($res, 'Datos invalidos', 422, $errores);
        }

        $existe = $this->db->prepare('SELECT id FROM usuarios WHERE email = :email');
        $existe->execute(['email' => $email]);
        if ($existe->fetch()) {
            return $this->error($res, 'El email ya esta registrado', 409);
        }

        $stmt = $this->db->prepare(
            'INSERT INTO usuarios (nombre, email, password_hash, empresa, cuit)
             VALUES (:nombre, :email, :hash, :empresa, :cuit)'
        );
        $stmt->execute([
            'nombre'  => $nombre,
            'email'   => $email,
            'hash'    => password_hash($password, PASSWORD_DEFAULT), // bcrypt con sal aleatoria
            'empresa' => $empresa,
            'cuit'    => $cuit !== '' ? $cuit : null,
        ]);

        return $this->json($res, [
            'id'      => (int) $this->db->lastInsertId(),
            'mensaje' => 'Cuenta creada correctamente',
        ], 201);
    }

    /** POST /api/auth/login */
    public function login(Request $req, Response $res): Response
    {
        $d = $this->body($req);
        $email    = strtolower(trim((string) ($d['email'] ?? '')));
        $password = (string) ($d['password'] ?? '');

        if ($email === '' || $password === '') {
            return $this->error($res, 'Email y contrasena son obligatorios', 422);
        }

        $stmt = $this->db->prepare(
            'SELECT id, nombre, email, password_hash, empresa, rol,
                    intentos_fallidos, bloqueado_hasta
               FROM usuarios WHERE email = :email'
        );
        $stmt->execute(['email' => $email]);
        $u = $stmt->fetch();

        $ahora = date('Y-m-d H:i:s');

        // Cuenta bloqueada temporalmente por intentos fallidos
        if ($u && $u['bloqueado_hasta'] !== null && $u['bloqueado_hasta'] > $ahora) {
            return $this->error($res, 'Cuenta bloqueada temporalmente. Intente mas tarde.', 423);
        }

        // Mismo mensaje si el email no existe o la clave es incorrecta (evita enumerar usuarios)
        if (!$u || !password_verify($password, $u['password_hash'])) {
            if ($u) {
                $this->registrarIntentoFallido((int) $u['id'], (int) $u['intentos_fallidos']);
            }
            return $this->error($res, 'Credenciales invalidas', 401);
        }

        // Login correcto: reinicia contador y actualiza el hash si el algoritmo cambio
        $upd = $this->db->prepare(
            'UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = :id'
        );
        $upd->execute(['id' => $u['id']]);

        if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
            $re = $this->db->prepare('UPDATE usuarios SET password_hash = :h WHERE id = :id');
            $re->execute(['h' => password_hash($password, PASSWORD_DEFAULT), 'id' => $u['id']]);
        }

        $emitido = time();
        $expira  = $emitido + (int) $this->jwtConfig['expiracion'];
        $token = JWT::encode([
            'iss'     => $this->jwtConfig['issuer'],
            'sub'     => (int) $u['id'],
            'iat'     => $emitido,
            'exp'     => $expira,
            'empresa' => $u['empresa'],
            'rol'     => $u['rol'],
        ], $this->jwtConfig['secret'], 'HS256');

        return $this->json($res, [
            'token'     => $token,
            'expira_en' => date(DATE_ATOM, $expira),
            'usuario'   => [
                'id'      => (int) $u['id'],
                'nombre'  => $u['nombre'],
                'email'   => $u['email'],
                'empresa' => $u['empresa'],
                'rol'     => $u['rol'],
            ],
        ]);
    }

    /** GET /api/auth/me */
    public function me(Request $req, Response $res): Response
    {
        $stmt = $this->db->prepare(
            'SELECT id, nombre, email, empresa, cuit, rol, mfa_habilitado, creado_en
               FROM usuarios WHERE id = :id'
        );
        $stmt->execute(['id' => $this->usuarioId($req)]);
        $u = $stmt->fetch();

        if (!$u) {
            return $this->error($res, 'Usuario no encontrado', 404);
        }
        $u['id'] = (int) $u['id'];
        $u['mfa_habilitado'] = (bool) $u['mfa_habilitado'];
        return $this->json($res, $u);
    }

    private function registrarIntentoFallido(int $id, int $intentosPrevios): void
    {
        $intentos = $intentosPrevios + 1;
        $bloqueo  = null;
        if ($intentos >= self::MAX_INTENTOS) {
            $bloqueo  = date('Y-m-d H:i:s', time() + self::MINUTOS_BLOQUEO * 60);
            $intentos = 0;
        }
        $stmt = $this->db->prepare(
            'UPDATE usuarios SET intentos_fallidos = :i, bloqueado_hasta = :b WHERE id = :id'
        );
        $stmt->execute(['i' => $intentos, 'b' => $bloqueo, 'id' => $id]);
    }
}
