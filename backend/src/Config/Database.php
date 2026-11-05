<?php
declare(strict_types=1);

namespace App\Config;

use PDO;

/**
 * Crea la conexion PDO a MySQL con opciones seguras.
 */
final class Database
{
    public static function conectar(array $cfg): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'],
            $cfg['port'],
            $cfg['name'],
            $cfg['charset']
        );

        return new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // los errores lanzan excepciones
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // filas como arrays asociativos
            PDO::ATTR_EMULATE_PREPARES   => false,                  // consultas preparadas reales
        ]);
    }
}
