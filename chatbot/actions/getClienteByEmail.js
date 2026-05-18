const fetchJson = async (url, bp) => {
  if (bp && bp.http && typeof bp.http.get === 'function') {
    const response = await bp.http.get(url);
    return response.data || response;
  }

  const response = await fetch(url);
  return response.json();
};

/**
 * Botpress action: obtiene un cliente por email.
 * Parámetros opcionales:
 * - apiUrl: URL completa para el endpoint PHP.
 * - email: correo del cliente.
 * Si no se provee email, se busca en `event.text`.
 */
module.exports = async (bp, state, event, params) => {
  const baseUrl = params.apiUrl || 'http://127.0.0.1:8888/api/botpress.php';
  const email = params.email || event.text || '';
  const apiUrl = `${baseUrl}?action=get_cliente&email=${encodeURIComponent(email)}`;
  const cliente = await fetchJson(apiUrl, bp);
  state.cliente = cliente;
  return { state };
};