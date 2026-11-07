/**
 * Verifica el mismo token JWT que emite el backend PHP (firmado con HS256)
 * y que el abono del cliente este al dia (misma regla que App\Servicios\Abono en PHP).
 * Si todo esta bien, deja el id del usuario en req.usuarioId.
 */
const jwt = require('jsonwebtoken');

/** Fecha local de hoy en formato AAAA-MM-DD. */
const hoy = () => new Date().toLocaleDateString('en-CA');

/** true si la cuenta no puede usar el sistema: abono vencido o nunca pagado (el personal no paga). */
function cuentaBloqueada(rol, abonoHasta) {
  if (rol === 'staff') return false;
  return !abonoHasta || abonoHasta < hoy();
}

function mensajeBloqueo(abonoHasta) {
  if (!abonoHasta) return 'Tu cuenta todavía no tiene un abono activo. Comunicate con GalviFintech para habilitarla.';
  const [a, m, d] = abonoHasta.split('-');
  return 'Cuenta suspendida por falta de pago. Tu abono venció el' ${d}/${m}/${a}'. Comunicate con GalviFintech para renovarlo'.;
}

function crearAuth({ secret, issuer }, db) {
  return async function requiereLogin(req, res, next) {
    const m = /^Bearer\s+(\S+)$/i.exec(req.get('Authorization') || '');
    if (!m) return res.status(401).json({ error: 'Falta el token de acceso' });

    let datos;
    try {
      datos = jwt.verify(m[1], secret, { algorithms: ['HS256'], issuer });
    } catch {
      return res.status(401).json({ error: 'Token invalido o vencido' });
    }

    try {
      const [filas] = await db.execute('SELECT rol, abono_hasta FROM usuarios WHERE id = ?', [Number(datos.sub)]);
      if (filas.length === 0) return res.status(401).json({ error: 'Token invalido o vencido' });
      const { rol, abono_hasta: abonoHasta } = filas[0];
      if (cuentaBloqueada(rol, abonoHasta)) {
        return res.status(403).json({ error: mensajeBloqueo(abonoHasta), codigo: 'cuenta_suspendida' });
      }
      req.usuarioId = Number(datos.sub);
      return next();
    } catch (err) {
      return next(err); // error de base de datos: lo atiende el manejador de errores
    }
  };
}

module.exports = { crearAuth, cuentaBloqueada };
