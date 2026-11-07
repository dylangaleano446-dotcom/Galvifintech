<?php
declare(strict_types=1);

namespace App\Servicios;

/**
 * Validacion de los datos de alta de un usuario (registro publico y alta desde el panel).
 */
final class ValidadorUsuario
{
    /** @return array{0: array, 1: array} [datos normalizados, errores] */
    public static function validar(array $d): array
    {
        $datos = [
            'nombre'   => trim((string) ($d['nombre'] ?? '')),
            'email'    => strtolower(trim((string) ($d['email'] ?? ''))),
            'password' => (string) ($d['password'] ?? ''),
            'empresa'  => trim((string) ($d['empresa'] ?? '')),
            'cuit'     => preg_replace('/\D/', '', (string) ($d['cuit'] ?? '')),
        ];

        $errores = [];
        if (mb_strlen($datos['nombre']) < 3 || mb_strlen($datos['nombre']) > 100) {
            $errores['nombre'] = 'Debe tener entre 3 y 100 caracteres.';
        }
        if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL) || strlen($datos['email']) > 150) {
            $errores['email'] = 'Email invalido.';
        }
        $p = $datos['password'];
        if (strlen($p) < 8 || !preg_match('/[A-Za-z]/', $p) || !preg_match('/\d/', $p)) {
            $errores['password'] = 'Minimo 8 caracteres, con al menos una letra y un numero.';
        }
        if (mb_strlen($datos['empresa']) < 2 || mb_strlen($datos['empresa']) > 150) {
            $errores['empresa'] = 'Debe tener entre 2 y 150 caracteres.';
        }
        if ($datos['cuit'] !== '' && strlen($datos['cuit']) !== 11) {
            $errores['cuit'] = 'El CUIT debe tener 11 digitos.';
        }
        return [$datos, $errores];
    }
}
