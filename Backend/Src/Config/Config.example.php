<?php
/**
 * GalviFintech Portal - Configuración de ejemplo.
 * Copiar este archivo como config.php y completar con los datos reales.
 * config.php NO se sube a GitHub (está en .gitignore).
 */
declare(strict_types=1);

return [
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'name'    => 'galvifintech',
        'user'    => 'root',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],
    'jwt' => [
        'secret'     => 'CAMBIAR_por_una_clave_aleatoria_de_al_menos_32_caracteres',
        'issuer'     => 'galvifintech',
        'expiracion' => 60 * 60 * 8, // 8 horas
    ],
    'app' => [
        'base_path'   => '/galvifintech/backend/public',
        'cors_origin' => 'http://localhost',
        'debug'       => true,
    ],
];
