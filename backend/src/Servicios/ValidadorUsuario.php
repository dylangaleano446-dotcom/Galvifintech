[14:55, 7/11/2026] Dylan: <?php
declare(strict_types=1);

namespace App\Servicios;

use DateTimeImmutable;

/**
 * Reglas del abono mensual: estado de la cuenta, precios y periodo que cubre cada pago.
 * Sin dias de gracia: la cuenta se suspende al dia siguiente de abono_hasta.
 */
final class Abono
{
    public const PLANES     = ['basico' => 25.0, 'pyme' => 45.0, 'empresa' => 85.0]; // USD por mes, sin IVA
    public const DESCUENTOS = [1 => 0.0, 6 => 0.05, 12 => 0.15];                       // por pago adelantado
    public const IVA        = 0.21;
    public const DIAS_AVISO = 7;                                                       // aviso "por vencer"

    /**
     * Estado de la cuenta segun su rol y la fecha hasta la que tiene pago.
     * @return array{estado: string, abono_hasta: ?string, dias_restantes: ?int}
     */
    public static function estado(string $rol, ?string $abonoHasta, ?string $hoy = null): array
    {
        $hoy ??= date('Y-m-d');

        if ($rol === 'staff') {
            return ['estado' => 'staff', 'abono_hasta' => null, 'dias_restantes' => null];
        }
        if ($abonoHasta === null) {
            return ['estado' => 'sin_abono', 'abono_hasta' => null, 'dias_restantes' => null];
        }

        $dias = (int) (new DateTimeImmutable($hoy))->diff(new DateTimeImmutable($abonoHasta))->format('%r%a');
        $estado = match (true) {
            $dias < 0                => 'suspendido',
            $dias <= self::DIAS_AVISO => 'por_vencer',
            default                  => 'activo',
        };
        return ['estado' => $estado, 'abono_hasta' => $abonoHasta, 'dias_restantes' => $dias];
    }

    /** true si la cuenta no puede usar el sistema (vencida o nunca pago). */
    public static function bloqueada(string $rol, ?string $abonoHasta): bool
    {
        $e = self::estado($rol, $abonoHasta)['estado'];
        return $e === 'suspendido' || $e === 'sin_abono';
    }

    /** Mensaje que ve el cliente cuando su cuenta esta bloqueada. */
    public static function mensajeBloqueo(?string $abonoHasta): string
    {
        if ($abonoHasta === null) {
            return 'Tu cuenta todavía no tiene un abono activo. Comunicate con GalviFintech para habilitarla.';
        }
        $fecha = (new DateTimeImmutable($abonoHasta))->format('d/m/Y');
        return "Cuenta suspendida por falta de pago. Tu abono venció el $fecha. Comunicate con GalviFintech para renovarlo.";
    }

    /** Importe sugerido en USD con descuento e IVA (sin usuarios ni modulos extra). */
    public static function montoSugerido(string $plan, int $meses): float
    {
        $neto = self::PLANES[$plan] * $meses * (1 - self::DESCUENTOS[$meses]);
        return round($neto * (1 + self::IVA), 2);
    }

    /**
     * Periodo que cubre un pago nuevo.
     * Si la cuenta esta al dia, el pago se suma a continuacion del vencimiento actual;
     * si esta vencida (o nunca pago), empieza a contar desde hoy.
     * @return array{0: string, 1: string} [desde, hasta] en formato AAAA-MM-DD
     */
    public static function nuevoPeriodo(?string $abonoHasta, int $meses, ?string $hoy = null): array
    {
        $hoy ??= date('Y-m-d');
        $desde = ($abonoHasta !== null && $abonoHasta >= $hoy)
            ? (new DateTimeImmutable($abonoHasta))->modify('+1 day')
            : new DateTimeImmutable($hoy);

        // Mismo dia N meses despues menos un dia (si ese mes es mas corto, hasta su ultimo dia)
        $y = (int) $desde->format('Y');
        $m = (int) $desde->format('n') + $meses;
        $y += intdiv($m - 1, 12);
        $m = ($m - 1) % 12 + 1;
        $ultimoDia = (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $y, $m)))->format('t');
        $dia = (int) $desde->format('j');
        $hasta = $dia > $ultimoDia
            ? new DateTimeImmutable(sprintf('%04d-%02d-%02d', $y, $m, $ultimoDia))
            : (new DateTimeImmutable(sprintf('%04d-%02d-%02d', $y, $m, $dia)))->modify('-1 day');

        return [$desde->format('Y-m-d'), $hasta->format('Y-m-d')];
    }
}
[14:57, 7/11/2026] Dylan: <?php
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
        if (mb_strlen($datos['nombre']) < 3 || mb_st…
