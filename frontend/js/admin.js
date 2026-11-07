/**
 * GalviFintech Portal - Panel de administracion (personal de GalviFintech)
 * Clientes, estado del abono, registro de pagos y alta de clientes.
 */
Sesion.exigir();
// Los clientes no tienen acceso a este panel
if (Sesion.usuario()?.rol !== 'staff') window.location.href = 'dashboard.html';

const $ = (id) => document.getElementById(id);
const PRECIOS = { basico: 25, pyme: 45, empresa: 85 };   // USD por mes, sin IVA (igual que el backend)
const DESCUENTOS = { 1: 0, 6: 0.05, 12: 0.15 };
const NOMBRE_PLAN = { basico: 'Básico', pyme: 'PyME', empresa: 'Empresa' };
const MEDIOS = { transferencia: 'Transferencia', mercadopago: 'Mercado Pago', efectivo: 'Efectivo', otro: 'Otro' };
const usd = (v) => 'USD ' + (Number(v).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
let clientes = [];

// ---------------------------------------------------------------------
// Inicio
// ---------------------------------------------------------------------
document.addEventListener('DOMContentLoaded', () => {
  const u = Sesion.usuario();
  $('nombre-usuario').textContent = u ? u.nombre : '';
  $('btn-salir').addEventListener('click', () => Sesion.cerrar());

  const formPago = $('form-pago');
  formPago.addEventListener('submit', registrarPago);
  formPago.usuario_id.addEventListener('change', () => {
    const c = clientes.find((x) => x.id === Number(formPago.usuario_id.value));
    if (c && c.plan) formPago.plan.value = c.plan;
    sugerirMonto();
  });
  formPago.plan.addEventListener('change', sugerirMonto);
  formPago.meses.addEventListener('change', sugerirMonto);

  $('form-cliente').addEventListener('submit', crearCliente);

  cargarTodo();
});

async function cargarTodo(seleccionarId = null) {
  try {
    await Promise.all([cargarClientes(seleccionarId), cargarPagos()]);
  } catch (err) {
    mostrarMensaje(err.message, 'error');
  }
}

// ---------------------------------------------------------------------
// Clientes y estado del abono
// ---------------------------------------------------------------------
function etiquetaEstado(c) {
  switch (c.estado) {
    case 'activo':     return ['Activo', 'bg-blue-50 text-blue-700'];
    case 'por_vencer': return [c.dias_restantes === 0 ? 'Vence hoy' : 'Vence en ' + (c.dias_restantes) + ' d', 'bg-red-50 text-red-600'];
    case 'suspendido': return ['Suspendido', 'bg-red-600 text-white'];
    default:           return ['Sin abono', 'bg-slate-100 text-slate-600'];
  }
}

async function cargarClientes(seleccionarId) {
  const r = await api('/admin/clientes');
  clientes = r.clientes;

  ['activo', 'por_vencer', 'suspendido', 'sin_abono'].forEach((k) => { $('kpi-' + (k)).textContent = r.resumen[k]; });
  // Las alertas se pintan de rojo solo si hay casos
  $('kpi-por_vencer').className = 'mt-1 text-2xl font-bold ' + (r.resumen.por_vencer ? 'text-red-500' : 'text-slate-500');
  $('kpi-suspendido').className = 'mt-1 text-2xl font-bold ' + (r.resumen.suspendido ? 'text-red-600' : 'text-slate-500');

  const tbody = $('tabla-clientes');
  tbody.innerHTML = '';
  if (clientes.length === 0) {
    tbody.innerHTML = '<tr><td colspan="5" class="py-3 text-slate-400">Todavía no hay clientes.</td></tr>';
  }
  clientes.forEach((c) => {
    const fila = document.createElement('tr');
    fila.innerHTML = ' <td class="py-2"><span class="block font-medium"></span><span class="block text-xs text-slate-400"></span></td> <td class="py-2"></td> <td class="py-2 whitespace-nowrap"></td> <td class="py-2"><span class="rounded-full px-2 py-0.5 text-xs font-semibold whitespace-nowrap"></span></td> <td class="py-2 text-right"><button class="rounded-lg border border-slate-300 px-2 py-1 text-xs hover:bg-slate-50 whitespace-nowrap">Registrar pago</button></td>';
    // textContent: los datos que cargan los clientes nunca se interpretan como HTML (evita XSS)
    fila.querySelector('td:nth-child(1) span:first-child').textContent = c.empresa;
    fila.querySelector('td:nth-child(1) span:last-child').textContent = c.email;
    fila.querySelector('td:nth-child(2)').textContent = NOMBRE_PLAN[c.plan] || '—';
    fila.querySelector('td:nth-child(3)').textContent = formatoFecha(c.abono_hasta);
    const [texto, clases] = etiquetaEstado(c);
    const badge = fila.querySelector('td:nth-child(4) span');
    badge.textContent = texto;
    badge.className += ' ' + (clases);
    fila.querySelector('button').addEventListener('click', () => elegirParaPago(c.id));
    tbody.append(fila);
  });

  // Lista del formulario de pago
  const select = $('form-pago').usuario_id;
  const anterior = seleccionarId ?? Number(select.value);
  select.innerHTML = '';
  clientes.forEach((c) => {
    const op = document.createElement('option');
    op.value = c.id;
    op.textContent = (c.empresa) + ' (' + (etiquetaEstado(c)[0]) + ')';
    select.append(op);
  });
  if (clientes.some((c) => c.id === anterior)) select.value = anterior;
  select.dispatchEvent(new Event('change'));
}

function elegirParaPago(id) {
  const form = $('form-pago');
  form.usuario_id.value = id;
  form.usuario_id.dispatchEvent(new Event('change'));
  $('caja-pago').scrollIntoView({ behavior: 'smooth', block: 'center' });
  form.monto_usd.focus();
}

/** Monto sugerido: precio del plan × meses, con descuento por pago adelantado e IVA 21 %. */
function sugerirMonto() {
  const form = $('form-pago');
  const meses = Number(form.meses.value);
  const neto = PRECIOS[form.plan.value] * meses * (1 - DESCUENTOS[meses]);
  form.monto_usd.value = (Math.round(neto * 1.21 * 100) / 100).toFixed(2);
}

// ---------------------------------------------------------------------
// Registrar pago (reactiva o extiende el abono)
// ---------------------------------------------------------------------
async function registrarPago(e) {
  e.preventDefault();
  const datos = Object.fromEntries(new FormData(e.target));
  datos.usuario_id = Number(datos.usuario_id);
  datos.meses = Number(datos.meses);
  datos.monto_usd = Number(datos.monto_usd);
  if (!datos.usuario_id) {
    mostrarMensaje('Elegí un cliente.', 'error');
    return;
  }
  try {
    const r = await api('/admin/pagos', { method: 'POST', body: datos });
    mostrarMensaje(r.mensaje, 'ok');
    e.target.comprobante.value = '';
    await cargarTodo(datos.usuario_id);
  } catch (err) {
    const detalle = Object.values(err.detalles || {}).join(' ');
    mostrarMensaje((err.message) + '. ' + (detalle), 'error');
  }
}

// ---------------------------------------------------------------------
// Alta de cliente
// ---------------------------------------------------------------------
async function crearCliente(e) {
  e.preventDefault();
  const datos = Object.fromEntries(new FormData(e.target));
  try {
    const r = await api('/admin/clientes', { method: 'POST', body: datos });
    mostrarMensaje(r.mensaje, 'ok');
    e.target.reset();
    await cargarTodo(r.id); // queda elegido en "Registrar pago"
  } catch (err) {
    const detalle = Object.values(err.detalles || {}).join(' ');
    mostrarMensaje((err.message) + '. ' + (detalle), 'error');
  }
}

// ---------------------------------------------------------------------
// Historial de pagos
// ---------------------------------------------------------------------
async function cargarPagos() {
  const pagos = await api('/admin/pagos');
  const tbody = $('tabla-pagos');
  tbody.innerHTML = '';
  if (pagos.length === 0) {
    tbody.innerHTML = '<tr><td colspan="6" class="py-3 text-slate-400">Todavía no hay pagos registrados.</td></tr>';
    return;
  }
  pagos.forEach((p) => {
    const fila = document.createElement('tr');
    const celdas = [
      formatoFecha(p.creado_en),
      p.empresa,
      (NOMBRE_PLAN[p.plan]) + ' · ' + (p.meses) + ' ' + (p.meses === 1 ? 'mes' : 'meses'),
      (formatoFecha(p.periodo_desde)) + ' al ' + (formatoFecha(p.periodo_hasta)),
      MEDIOS[p.medio_pago] + (p.comprobante ? ' (' + (p.comprobante) + ')' : ''),
      usd(p.monto_usd),
    ];
    celdas.forEach((texto, i) => {
      const td = fila.insertCell();
      td.className = 'py-2 ' + (i === 5 ? 'text-right whitespace-nowrap' : '') + ' ' + (i === 0 || i === 3 ? 'whitespace-nowrap' : '');
      td.textContent = texto;
    });
    tbody.append(fila);
  });
}

// ---------------------------------------------------------------------
// Mensajes
// ---------------------------------------------------------------------
function mostrarMensaje(texto, tipo) {
  const caja = $('mensaje');
  caja.textContent = texto;
  caja.className = 'rounded-lg px-4 py-3 text-sm ' + (tipo === 'ok'
    ? 'bg-blue-50 border border-blue-200 text-blue-800'
    : 'bg-red-50 border border-red-200 text-red-700');
  clearTimeout(mostrarMensaje.timer);
  mostrarMensaje.timer = setTimeout(() => caja.classList.add('hidden'), 6000);
}
