/**
 * GalviFintech Portal - Logica del Dashboard (UC02, UC03 y UC05)
 */
Sesion.exigir();

const $ = (id) => document.getElementById(id);
let graficoEvolucion = null;
let graficoCategorias = null;

const COLORES = ['#172554', '#1D4ED8', '#64748B', '#93C5FD', '#334155', '#94A3B8', '#3B82F6', '#CBD5E1'];
/** Fecha local de hoy en formato AAAA-MM-DD (toISOString usaria UTC y podria dar el dia siguiente) */
const hoyISO = () => new Date().toLocaleDateString('en-CA');
const MESES = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

// ---------------------------------------------------------------------
// Inicio
// ---------------------------------------------------------------------
document.addEventListener('DOMContentLoaded', () => {
  const usuario = Sesion.usuario();
  $('nombre-empresa').textContent = usuario ? `${usuario.nombre} · ${usuario.empresa}` : '';

  const hoy = new Date();
  $('periodo').value = `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}`;
  document.querySelector('[name="fecha"]').value = hoyISO();

  $('periodo').addEventListener('change', cargarTodo);
  $('btn-salir').addEventListener('click', () => Sesion.cerrar());
  $('btn-exportar').addEventListener('click', exportarCsv);
  $('form-transaccion').addEventListener('submit', guardarTransaccion);

  cargarTodo();
});

function periodoSeleccionado() {
  const [anio, mes] = $('periodo').value.split('-').map(Number);
  const ultimoDia = new Date(anio, mes, 0).getDate();
  const mm = String(mes).padStart(2, '0');
  return { anio, mes, desde: `${anio}-${mm}-01`, hasta: `${anio}-${mm}-${ultimoDia}` };
}

async function cargarTodo() {
  try {
    await Promise.all([cargarResumen(), cargarMovimientos()]);
  } catch (err) {
    mostrarMensaje(err.message, 'error');
  }
}

// ---------------------------------------------------------------------
// UC03 - Resumen, KPI y graficos
// ---------------------------------------------------------------------
async function cargarResumen() {
  const { anio, mes } = periodoSeleccionado();
  const r = await api(`/dashboard/resumen?anio=${anio}&mes=${mes}`);

  $('kpi-ingresos').textContent = formatoPesos(r.ingresos);
  $('kpi-gastos').textContent = formatoPesos(r.gastos);
  $('kpi-balance').textContent = formatoPesos(r.balance);
  $('kpi-balance').className = `mt-1 text-2xl font-bold ${r.balance >= 0 ? 'text-blue-700' : 'text-red-600'}`;

  dibujarEvolucion(r.evolucion);
  dibujarCategorias(r.gastos_categoria);
  dibujarPresupuestos(r.presupuestos);
}

function dibujarEvolucion(evolucion) {
  const etiquetas = evolucion.map((e) => {
    const [a, m] = e.periodo.split('-');
    return `${MESES[Number(m) - 1]} ${a.slice(2)}`;
  });
  if (graficoEvolucion) graficoEvolucion.destroy(); // evita graficos superpuestos
  graficoEvolucion = new Chart($('grafico-evolucion'), {
    type: 'bar',
    data: {
      labels: etiquetas,
      datasets: [
        { label: 'Ingresos', data: evolucion.map((e) => e.ingresos), backgroundColor: '#1D4ED8', borderRadius: 6 },
        { label: 'Gastos', data: evolucion.map((e) => e.gastos), backgroundColor: '#DC2626', borderRadius: 6 },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { tooltip: { callbacks: { label: (c) => `${c.dataset.label}: ${formatoPesos(c.parsed.y)}` } } },
      scales: { y: { beginAtZero: true, ticks: { callback: (v) => formatoPesos(v) } } },
    },
  });
}

function dibujarCategorias(categorias) {
  if (graficoCategorias) graficoCategorias.destroy();
  graficoCategorias = new Chart($('grafico-categorias'), {
    type: 'doughnut',
    data: {
      labels: categorias.map((c) => c.categoria),
      datasets: [{ data: categorias.map((c) => c.total), backgroundColor: COLORES }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'bottom' },
        tooltip: { callbacks: { label: (c) => `${c.label}: ${formatoPesos(c.parsed)}` } },
      },
    },
  });
}

function dibujarPresupuestos(presupuestos) {
  const lista = $('lista-presupuestos');
  lista.innerHTML = '';
  if (presupuestos.length === 0) {
    lista.innerHTML = '<li class="text-slate-400">No hay presupuestos cargados para este mes.</li>';
    return;
  }
  const colorEstado = { ok: 'bg-blue-700', alerta: 'bg-red-300', excedido: 'bg-red-600' };
  presupuestos.forEach((p) => {
    const li = document.createElement('li');
    const cabecera = document.createElement('div');
    cabecera.className = 'flex justify-between';
    const nombre = document.createElement('span');
    nombre.className = 'font-medium';
    nombre.textContent = p.categoria; // textContent evita inyeccion de HTML (XSS)
    const cifras = document.createElement('span');
    cifras.className = p.estado === 'excedido' ? 'text-red-600 font-semibold' : 'text-slate-500';
    cifras.textContent = `${formatoPesos(p.gastado)} / ${formatoPesos(p.limite)} (${p.porcentaje}%)`;
    cabecera.append(nombre, cifras);

    const barra = document.createElement('div');
    barra.className = 'mt-1 h-2 rounded-full bg-slate-200';
    const relleno = document.createElement('div');
    relleno.className = `h-2 rounded-full ${colorEstado[p.estado]}`;
    relleno.style.width = `${Math.min(p.porcentaje, 100)}%`;
    barra.append(relleno);

    li.append(cabecera, barra);
    lista.append(li);
  });
}

// ---------------------------------------------------------------------
// Movimientos del periodo
// ---------------------------------------------------------------------
async function cargarMovimientos() {
  const { desde, hasta } = periodoSeleccionado();
  const r = await api(`/transacciones?desde=${desde}&hasta=${hasta}`);
  const tbody = $('tabla-movimientos');
  tbody.innerHTML = '';

  if (r.total === 0) {
    tbody.innerHTML = '<tr><td colspan="4" class="py-3 text-slate-400">Sin movimientos en el período.</td></tr>';
    return;
  }
  r.datos.forEach((t) => {
    const fila = document.createElement('tr');
    const [a, m, d] = t.fecha.split('-');
    fila.innerHTML = `
      <td class="py-2 whitespace-nowrap">${d}/${m}</td>
      <td class="py-2"><span class="block"></span><span class="block text-xs text-slate-400"></span></td>
      <td class="py-2 text-right whitespace-nowrap ${t.tipo === 'ingreso' ? 'text-blue-700' : 'text-red-600'}"></td>
      <td class="py-2 text-right"><button class="text-slate-400 hover:text-red-600" title="Eliminar">✕</button></td>`;
    fila.querySelector('td:nth-child(2) span:first-child').textContent = t.descripcion;
    fila.querySelector('td:nth-child(2) span:last-child').textContent = t.categoria;
    fila.querySelector('td:nth-child(3)').textContent = `${t.tipo === 'ingreso' ? '+' : '−'} ${formatoPesos(t.monto)}`;
    fila.querySelector('button').addEventListener('click', () => eliminarTransaccion(t.id));
    tbody.append(fila);
  });
}

// ---------------------------------------------------------------------
// UC02 - Cargar transaccion
// ---------------------------------------------------------------------
async function guardarTransaccion(e) {
  e.preventDefault();
  const form = e.target;
  const datos = Object.fromEntries(new FormData(form));
  datos.monto = Number(datos.monto);

  try {
    await api('/transacciones', { method: 'POST', body: datos });
    mostrarMensaje('Movimiento registrado correctamente.', 'ok');
    form.reset();
    form.querySelector('[name="fecha"]').value = hoyISO();
    await cargarTodo();
  } catch (err) {
    const detalle = Object.values(err.detalles || {}).join(' ');
    mostrarMensaje(`${err.message}. ${detalle}`, 'error');
  }
}

async function eliminarTransaccion(id) {
  if (!confirm('¿Eliminar este movimiento? La acción no se puede deshacer.')) return;
  try {
    await api(`/transacciones/${id}`, { method: 'DELETE' });
    mostrarMensaje('Movimiento eliminado.', 'ok');
    await cargarTodo();
  } catch (err) {
    mostrarMensaje(err.message, 'error');
  }
}

// ---------------------------------------------------------------------
// UC05 - Exportar CSV (lo genera el servicio Node.js; se envia el mismo token)
// ---------------------------------------------------------------------
async function exportarCsv() {
  const { desde, hasta } = periodoSeleccionado();
  try {
    const resp = await fetch(`${API_NODE_URL}/reportes/transacciones.csv?desde=${desde}&hasta=${hasta}`, {
      headers: { Authorization: `Bearer ${Sesion.token()}` },
    });
    if (!resp.ok) throw new Error('No se pudo generar el reporte.');
    const archivo = await resp.blob();
    const enlace = document.createElement('a');
    enlace.href = URL.createObjectURL(archivo);
    enlace.download = `galvifintech_${desde}_${hasta}.csv`;
    enlace.click();
    URL.revokeObjectURL(enlace.href);
  } catch (err) {
    mostrarMensaje(err.message, 'error');
  }
}

// ---------------------------------------------------------------------
// Mensajes al usuario
// ---------------------------------------------------------------------
function mostrarMensaje(texto, tipo) {
  const caja = $('mensaje');
  caja.textContent = texto;
  caja.className = `rounded-lg px-4 py-3 text-sm ${tipo === 'ok'
    ? 'bg-blue-50 border border-blue-200 text-blue-800'
    : 'bg-red-50 border border-red-200 text-red-700'}`;
  clearTimeout(mostrarMensaje.timer);
  mostrarMensaje.timer = setTimeout(() => caja.classList.add('hidden'), 4000);
    }
