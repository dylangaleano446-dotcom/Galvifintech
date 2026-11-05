/**
 * Verifica el mismo token JWT que emite el backend PHP (firmado con HS256).
 * Si es valido, deja el id del usuario en req.usuarioId.
 */
const jwt = require('jsonwebtoken');

function crearAuth({ secret, issuer }) {
  return function requiereLogin(req, res, next) {
    const m = /^Bearer\s+(\S+)$/i.exec(req.get('Authorization') || '');
    if (!m) return res.status(401).json({ error: 'Falta el token de acceso' });

    try {
      const datos = jwt.verify(m[1], secret, { algorithms: ['HS256'], issuer });
      req.usuarioId = Number(datos.sub);
      return next();
    } catch {
      return res.status(401).json({ error: 'Token invalido o vencido' });
    }
  };
}

module.exports = { crearAuth };
