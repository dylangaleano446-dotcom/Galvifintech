/**
 * GalviFintech - Servicio Node.js (Express)
 * Responsabilidades: cotizador de abonos (UC04) y reportes CSV (UC05).
 * El login, las transacciones y el dashboard siguen en la API PHP (Slim 4).
 * Ambos servidores comparten la base MySQL y la clave del token JWT.
 */
const express = require('express');
const mysql = require('mysql2/promise');
const config = require('./config.json');
const { crearAuth } = require('./middleware/auth');
const { cotizar } = require('./logica/cotizador');
const { generarCsv } = require('./logica/csv');

const app = express();
const db = mysql.createPool({ ...config.db, dateStrings: true, connectionLimit: 5 });
const requiereLogin = crearAuth(config.jwt,db);
const FECHA = /^\d{4}-\d{2}-\d{2}$/;

// ---------------------------------------------------------------------
// Middlewares globales: CORS, cabeceras de seguridad y JSON
// ---------------------------------------------------------------------
app.use((req, res, next) => {
  res.set({
    'Access-Control-Allow-Origin': config.corsOrigin,
    'Access-Control-Allow-Headers': 'Content-Type, Authorization',
    'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
    'X-Content-Type-Options': 'nosniff',
    'X-Frame-Options': 'DENY',
  });
  if (req.method === 'OPTIONS') return res.sendStatus(204);
  return next();
});
app.use(express.json({ limit: '10kb' }));

// ---------------------------------------------------------------------
// Rutas
// ---------------------------------------------------------------------
app.get('/api/health', (req, res) => {
  res.json({ estado: 'ok', servicio: 'GalviFintech Node', version: '1.0.0' });
});

// UC04 - Cotizador (publico)
app.post('/api/cotizaciones', (req, res) => {
  const { errores, cotizacion } = cotizar(req.body);
  if (errores) return res.status(422).json({ error: 'Datos invalidos', detalles: errores });
  return res.json(cotizacion);
});

// UC05 - Exportacion CSV (privado)
app.get('/api/reportes/transacciones.csv', requiereLogin, async (req, res, next) => {
  try {
    const { desde, hasta, tipo } = req.query;
    const where = ['usuario_id = ?'];
    const params = [req.usuarioId];

    if (desde) {
      if (!FECHA.test(desde)) return res.status(422).json({ error: 'Filtros invalidos', detalles: { desde: 'Formato AAAA-MM-DD.' } });
      where.push('fecha >= ?'); params.push(desde);
    }
    if (hasta) {
      if (!FECHA.test(hasta)) return res.status(422).json({ error: 'Filtros invalidos', detalles: { hasta: 'Formato AAAA-MM-DD.' } });
      where.push('fecha <= ?'); params.push(hasta);
    }
    if (tipo) {
      if (!['ingreso', 'gasto'].includes(tipo)) return res.status(422).json({ error: 'Filtros invalidos', detalles: { tipo: 'Debe ser "ingreso" o "gasto".' } });
      where.push('tipo = ?'); params.push(tipo);
    }

    // Consulta preparada: los valores viajan separados del SQL (evita inyeccion SQL)
    const [filas] = await db.execute(
      `SELECT fecha, tipo, categoria, descripcion, medio_pago, comprobante, monto
         FROM transacciones WHERE ${where.join(' AND ')} ORDER BY fecha ASC, id ASC`,
      params,
    );

    const hoy = new Date().toISOString().slice(0, 10).replace(/-/g, '');
    res.set('Content-Type', 'text/csv; charset=utf-8');
    res.set('Content-Disposition', `attachment; filename="galvifintech_movimientos_${hoy}.csv"`);
    return res.send(generarCsv(filas));
  } catch (err) {
    return next(err);
  }
});

// Rutas inexistentes y errores inesperados: siempre JSON
app.use((req, res) => res.status(404).json({ error: 'Ruta no encontrada' }));
app.use((err, req, res, next) => { // eslint-disable-line no-unused-vars
  console.error(err);
  res.status(500).json({ error: 'Error interno del servidor' });
});

app.listen(config.puerto, () => {
  console.log(`GalviFintech Node escuchando en http://localhost:${config.puerto}`);
});
