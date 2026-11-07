/**
 * GalviFintech Portal - Cliente de la API REST
 * Centraliza la URL base, el token JWT y el manejo de errores de fetch().
 */
const API_URL = 'http://localhost/galvifintech/backend/public/api'; // API PHP (Slim 4)
const API_NODE_URL = 'http://localhost:3000/api';                    // Servicio Node.js (Express)

const Sesion = {
  guardar(token, usuario) {
    sessionStorage.setItem('gf_token', token);
    sessionStorage.setItem('gf_usuario', JSON.stringify(usuario));
  },
  token() {
    return sessionStorage.getItem('gf_token');
  },
  usuario() {
    return JSON.parse(sessionStorage.getItem('gf_usuario') || 'null');
  },
  cerrar() {
    sessionStorage.removeItem('gf_token');
    sessionStorage.removeItem('gf_usuario');
    window.location.href = 'login.html';
  },
  exigir() {
    // Si no hay sesion, vuelve al login
    if (!this.token()) window.location.href = 'login.html';
  },
  /** Pantalla de inicio segun el rol: el personal de GalviFintech va al panel de administracion. */
  inicio() {
    const u = this.usuario();
    return u && u.rol === 'staff' ? 'admin.html' : 'dashboard.html';
  },
  /** Cierra la sesion y deja un mensaje para mostrar en el login (ej.: cuenta suspendida). */
  cerrarConAviso(texto) {
    sessionStorage.setItem('gf_aviso', texto);
    this.cerrar();
  },
};

/**
 * Llama a la API. Lanza un Error con el mensaje del servidor si la respuesta no es 2xx.
 * @param {string} ruta    ej: '/transacciones'
 * @param {object} opciones { method, body, base }  base: API_URL (PHP) o API_NODE_URL (Node)
 */
async function api(ruta, { method = 'GET', body = null, base = API_URL } = {}) {
  const cabeceras = { Accept: 'application/json' };
  if (body !== null) cabeceras['Content-Type'] = 'application/json';
  if (Sesion.token()) cabeceras.Authorization = Bearer ${Sesion.token()};

  const respuesta = await fetch(base + ruta, {
    method,
    headers: cabeceras,
    body: body !== null ? JSON.stringify(body) : null,
  });

  // Token vencido o invalido: cerrar sesion
  if (respuesta.status === 401 && Sesion.token()) {
    Sesion.cerrar();
    throw new Error('La sesion expiro. Ingrese nuevamente.');
  }

  if (respuesta.status === 204) return null;

  const datos = await respuesta.json().catch(() => ({}));

  // Abono vencido con la sesion abierta: se cierra y el login explica el motivo
  if (respuesta.status === 403 && datos.codigo === 'cuenta_suspendida' && Sesion.token()) {
    Sesion.cerrarConAviso(datos.error);
    throw new Error(datos.error);
  }

  if (!respuesta.ok) {
    const error = new Error(datos.error || Error ${respuesta.status});
    error.detalles = datos.detalles || {};
    error.status = respuesta.status;
    throw error;
  }
  return datos;
}

/** Formatea una fecha AAAA-MM-DD como DD/MM/AAAA. */
function formatoFecha(iso) {
  if (!iso) return '—';
  const [a, m, d] = iso.slice(0, 10).split('-');
  return ${d}/${m}/${a};
}

/** Formatea un numero como pesos argentinos: $ 1.234.567,89 */
function formatoPesos(valor) {
  return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(valor);
}
