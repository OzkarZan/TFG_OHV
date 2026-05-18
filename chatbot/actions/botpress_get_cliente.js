/**
 * Botpress Action: Get Client Information by Email
 * 
 * Usage in Botpress:
 * - Pass parameter: email
 * - Result stored in workflow.cliente (object)
 * - Example: ${workflow.cliente.nombre}, ${workflow.cliente.id_cliente}
 * 
 * Parameters:
 * - email: client email to search (required)
 * 
 * Environment Variables (optional):
 * - BOTPRESS_API_URL: defaults to http://127.0.0.1:5500/chatbot
 */

const axios = require('axios');

const handler = async (bp, event, state) => {
  try {
    const email = state.workflow.email || event.text || '';

    if (!email || email.trim().length === 0) {
      state.workflow.cliente_error = true;
      state.workflow.respuesta = 'Por favor, proporciona un email para buscar.';
      return;
    }

    const apiBaseUrl = process.env.BOTPRESS_API_URL || 'http://127.0.0.1:8888/chatbot';
    const url = `${apiBaseUrl}/gestionar_clientes.php?action=obtener&email=${encodeURIComponent(email)}`;

    const response = await axios.get(url, {
      timeout: 10000,
      validateStatus: () => true
    });

    if (response.status === 200 && response.data && !response.data.message) {
      state.workflow.cliente = response.data;
      state.workflow.cliente_encontrado = true;
      state.workflow.respuesta = `Cliente encontrado: ${response.data.nombre} (${response.data.email})`;
    } else {
      state.workflow.cliente_encontrado = false;
      state.workflow.respuesta = response.data?.message || `No se encontró cliente con email: ${email}`;
    }
  } catch (error) {
    bp.logger.error('Error fetching cliente:', error.message);
    state.workflow.cliente_encontrado = false;
    state.workflow.respuesta = `Error al conectar con el servidor: ${error.message}`;
  }
};

module.exports = { handler, translations: {} };