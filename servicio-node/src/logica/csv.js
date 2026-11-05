/**
 * GalviFintech - Generacion del reporte CSV de movimientos (UC05).
 * Funcion pura: recibe las filas de la base y devuelve el texto del archivo.
 * Separador ';' y coma decimal para que Excel en espanol lo abra en columnas.
 */

const BOM = '\uFEFF'; // Excel muestra bien los acentos

/** Evita la "inyeccion de formulas" al abrir el CSV en Excel (OWASP CSV Injection). */
function neutralizarFormula(valor) {
  const texto = String(valor ?? '');
  return /^[=+\-@\t\r]/.test(texto) ? `'${texto}` : texto;
}

/** Encierra entre comillas los campos que lo necesitan (mismo criterio que fputcsv de PHP). */
function campo(valor) {
  const texto = String(valor ?? '');
  return /[;"\n\r\t ]/.test(texto) ? `"${texto.replace(/"/g, '""')}"` : texto;
}

const formatoMonto = (n) => Number(n).toFixed(2).replace('.', ',');

/**
 * @param {Array<{fecha, tipo, categoria, descripcion, medio_pago, comprobante, monto}>} filas
 * @returns {string} contenido del CSV
 */
function generarCsv(filas) {
  const lineas = [['Fecha', 'Tipo', 'Categoria', 'Descripcion', 'Medio de pago', 'Comprobante', 'Monto']];
  let balance = 0;

  for (const t of filas) {
    const monto = Number(t.monto);
    balance += t.tipo === 'ingreso' ? monto : -monto;
    lineas.push([
      t.fecha,
      t.tipo,
      neutralizarFormula(t.categoria),
      neutralizarFormula(t.descripcion),
      t.medio_pago,
      neutralizarFormula(t.comprobante),
      formatoMonto(monto),
    ]);
  }
  lineas.push(['', '', '', '', '', 'BALANCE', formatoMonto(balance)]);

  return BOM + lineas.map((l) => l.map(campo).join(';')).join('\n') + '\n';
}

module.exports = { generarCsv, neutralizarFormula };
