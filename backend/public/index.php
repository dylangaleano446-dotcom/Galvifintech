<?php
/**
 * GalviFintech Portal - API REST
 * Punto de entrada unico (front controller) de Slim Framework 4.
 * El cotizador y los reportes CSV los atiende el servicio Node.js (carpeta servicio-node).
 */
declare(strict_types=1);

use App\Config\Database;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\PresupuestoController;
use App\Controllers\TransaccionController;
use App\Middleware\AuthMiddleware;
use App\Middleware\CorsMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;

require __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set('America/Argentina/Buenos_Aires');

$config = require __DIR__ . '/../src/Config/config.php';
$db     = Database::conectar($config['db']);

// ---------------------------------------------------------------------
// Aplicacion y middlewares globales
// ---------------------------------------------------------------------
$app = AppFactory::create();
$app->setBasePath($config['app']['base_path']);

$app->addBodyParsingMiddleware();   // convierte el JSON del cuerpo en array
$app->addRoutingMiddleware();

$errores = $app->addErrorMiddleware($config['app']['debug'], true, true);
$errores->getDefaultErrorHandler()->forceContentType('application/json');

$app->add(new CorsMiddleware($config['app']['cors_origin'])); // se agrega al final: se ejecuta primero

// ---------------------------------------------------------------------
// Controladores (se crean una vez y reciben la conexion PDO)
// ---------------------------------------------------------------------
$auth          = new AuthController($db, $config['jwt']);
$transacciones = new TransaccionController($db);
$dashboard     = new DashboardController($db);
$presupuestos  = new PresupuestoController($db);
$requiereLogin = new AuthMiddleware($config['jwt']);

// ---------------------------------------------------------------------
// Rutas
// ---------------------------------------------------------------------
$app->group('/api', function (RouteCollectorProxy $api) use (
    $auth, $transacciones, $dashboard, $presupuestos, $requiereLogin
) {
    // Estado de la API (sirve para comprobar que Apache + Slim funcionan)
    $api->get('/health', function (Request $req, Response $res) {
        $res->getBody()->write(json_encode(['estado' => 'ok', 'servicio' => 'GalviFintech API', 'version' => '1.0.0']));
        return $res->withHeader('Content-Type', 'application/json');
    });

    // Rutas publicas
    $api->post('/auth/register', [$auth, 'registrar']);
    $api->post('/auth/login',    [$auth, 'login']);

    // Rutas privadas: requieren token JWT
    $api->group('', function (RouteCollectorProxy $privado) use ($auth, $transacciones, $dashboard, $presupuestos) {
        $privado->get('/auth/me', [$auth, 'me']);

        $privado->get('/transacciones',                 [$transacciones, 'listar']);
        $privado->post('/transacciones',                [$transacciones, 'crear']);
        $privado->put('/transacciones/{id:[0-9]+}',     [$transacciones, 'actualizar']);
        $privado->delete('/transacciones/{id:[0-9]+}',  [$transacciones, 'eliminar']);

        $privado->get('/dashboard/resumen',             [$dashboard, 'resumen']);

        $privado->get('/presupuestos',                  [$presupuestos, 'listar']);
        $privado->post('/presupuestos',                 [$presupuestos, 'guardar']);
    })->add($requiereLogin);
});

$app->run();
