/**
 * GalviFintech - Logica del cotizador de abonos (UC04).
 * Funcion pura: recibe el pedido y devuelve la cotizacion o la lista de errores.
 * Precios en dolares (USD) por mes, sin IVA.
 */

const PLANES = {
  basico:  { nombre: 'Basico',  precio: 25, usuarios: 1,  incluye: [] },
  pyme:    { nombre: 'PyME',    precio: 45, usuarios: 5,  incluye: ['presupuestos', 'exportacion'] },
  empresa: { nombre: 'Empresa', precio: 85, usuarios: 15, incluye: ['presupuestos', 'exportacion', 'soporte_prioritario'] },
};

const MODULOS = {
  presupuestos:        { nombre: 'Presupuestos y alertas',      precio: 8 },
  exportacion:         { nombre: 'Exportacion de reportes CSV', precio: 10 },
  soporte_prioritario: { nombre: 'Soporte prioritario 24 h',    precio: 15 },
};

const PRECIO_USUARIO_EXTRA = 6;
const DESCUENTOS = { 1: 0, 6: 0.05, 12: 0.15 }; // por pago adelantado
const IVA = 0.21;
const MAX_USUARIOS = 100;

/** Redondea a 2 decimales evitando errores de coma flotante. */
const r2 = (n) => Math.round((n + Number.EPSILON) * 100) / 100;

function validar({ plan, usuarios, meses, modulos }) {
  const errores = {};
  if (!Object.prototype.hasOwnProperty.call(PLANES, plan)) errores.plan = 'Debe ser basico, pyme o empresa.';
  if (!Number.isInteger(usuarios) || usuarios < 1 || usuarios > MAX_USUARIOS) errores.usuarios = `Entre 1 y ${MAX_USUARIOS}.`;
  if (!Object.prototype.hasOwnProperty.call(DESCUENTOS, meses)) errores.meses = 'Debe ser 1, 6 o 12.';
  if (!Array.isArray(modulos) || modulos.some((m) => !Object.prototype.hasOwnProperty.call(MODULOS, m))) {
    errores.modulos = 'Modulo desconocido.';
  }
  return errores;
}

/**
 * @param {object} pedido { plan, usuarios, meses, modulos[] }
 * @returns {{ errores: object|null, cotizacion: object|null }}
 */
function cotizar(pedido = {}) {
  const datos = {
    plan: String(pedido.plan ?? ''),
    usuarios: Number(pedido.usuarios ?? 1),
    meses: Number(pedido.meses ?? 1),
    modulos: pedido.modulos ?? [],
  };
  const errores = validar(datos);
  if (Object.keys(errores).length > 0) return { errores, cotizacion: null };

  const plan = PLANES[datos.plan];
  const detalle = [{ concepto: `Plan ${plan.nombre} (${plan.usuarios} usuario/s incluido/s)`, importe: plan.precio }];

  const extras = Math.max(0, datos.usuarios - plan.usuarios);
  if (extras > 0) detalle.push({ concepto: `${extras} usuario/s adicional/es`, importe: extras * PRECIO_USUARIO_EXTRA });

  for (const m of new Set(datos.modulos)) {
    if (!plan.incluye.includes(m)) { // si el plan ya lo incluye, no se cobra
      detalle.push({ concepto: `Modulo: ${MODULOS[m].nombre}`, importe: MODULOS[m].precio });
    }
  }

  const mensual = detalle.reduce((suma, d) => suma + d.importe, 0);
  const bruto = mensual * datos.meses;
  const descuento = r2(bruto * DESCUENTOS[datos.meses]);
  const neto = r2(bruto - descuento);
  const iva = r2(neto * IVA);

  return {
    errores: null,
    cotizacion: {
      moneda: 'USD',
      plan: plan.nombre,
      usuarios: datos.usuarios,
      meses: datos.meses,
      detalle_mensual: detalle,
      subtotal_mensual: r2(mensual),
      subtotal_periodo: r2(bruto),
      descuento,
      neto,
      iva,
      total: r2(neto + iva),
      validez_dias: 15,
    },
  };
}

module.exports = { cotizar, PLANES, MODULOS };
