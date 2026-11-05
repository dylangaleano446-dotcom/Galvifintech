/**
 * Pruebas de la logica del servicio Node (sin base de datos ni servidor).
 * Ejecutar con:  node --test
 */
const test = require('node:test');
const assert = require('node:assert/strict');
const { cotizar } = require('../src/logica/cotizador');
const { generarCsv, neutralizarFormula } = require('../src/logica/csv');

test('cotiza plan PyME, 7 usuarios, 12 meses, con soporte prioritario', () => {
  const { errores, cotizacion } = cotizar({ plan: 'pyme', usuarios: 7, meses: 12, modulos: ['exportacion', 'soporte_prioritario'] });
  assert.equal(errores, null);
  assert.equal(cotizacion.subtotal_mensual, 72);
  assert.equal(cotizacion.descuento, 129.6);
  assert.equal(cotizacion.iva, 154.22);
  assert.equal(cotizacion.total, 888.62);
  // "exportacion" ya viene en el plan PyME: no se cobra
  assert.equal(cotizacion.detalle_mensual.length, 3);
});

test('plan Basico mensual sin extras', () => {
  const { cotizacion } = cotizar({ plan: 'basico', usuarios: 1, meses: 1, modulos: [] });
  assert.equal(cotizacion.total, 30.25); // 25 + 21 % IVA
});

test('rechaza datos invalidos con el detalle de cada campo', () => {
  const { errores, cotizacion } = cotizar({ plan: 'gratis', usuarios: 0, meses: 3, modulos: ['otro'] });
  assert.equal(cotizacion, null);
  assert.deepEqual(Object.keys(errores).sort(), ['meses', 'modulos', 'plan', 'usuarios']);
});

test('genera el CSV con BOM, separador ; y balance final', () => {
  const csv = generarCsv([
    { fecha: '2026-10-01', tipo: 'gasto', categoria: 'Alquiler', descripcion: 'Alquiler del local', medio_pago: 'transferencia', comprobante: null, monto: '350000.00' },
    { fecha: '2026-10-07', tipo: 'ingreso', categoria: 'Ventas', descripcion: 'Ventas semana 1', medio_pago: 'efectivo', comprobante: 'B-1', monto: '850000.00' },
  ]);
  assert.ok(csv.startsWith('\uFEFF'));
  const lineas = csv.trim().split('\n');
  assert.equal(lineas.length, 4);
  assert.equal(lineas[1], '2026-10-01;gasto;Alquiler;"Alquiler del local";transferencia;;350000,00');
  assert.equal(lineas[3], ';;;;;BALANCE;500000,00');
});

test('neutraliza formulas de Excel (CSV injection)', () => {
  assert.equal(neutralizarFormula('=SUMA(A1:A9)'), "'=SUMA(A1:A9)");
  assert.equal(neutralizarFormula('Ventas'), 'Ventas');
});
