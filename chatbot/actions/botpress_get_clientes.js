/**
 * Botpress Action: Get All Clients
 * 
 * Usage in Botpress:
 * - Call this action without parameters
 * - Result stored in workflow.clientes (array)
 * - Example: ${workflow.clientes[0].nombre}
 * 
 * Environment Variables (optional):
 * - BOTPRESS_API_URL: defaults to http://127.0.0.1:5500/chatbot
 */

const axios = require('axios');

const handler = async (bp, event, state) => {
  try {
    const apiBaseUrl = process.env.BOTPRESS_API_URL || 'http://127.0.0.1:8888/chatbot';
    const url = `${apiBaseUrl}/gestionar_clientes.php?action=listar`;

    const response = await axios.get(url, {
      timeout: 10000,
      validateStatus: () => true
    });

    if (response.status === 200 && response.data) {
      if (Array.isArray(response.data)) {
        state.workflow.clientes = response.data;
        state.workflow.clientes_encontrados = true;
        state.workflow.respuesta = `Encontré ${response.data.length} cliente(s) en la base de datos.`;
      } else {
        state.workflow.clientes_encontrados = false;
        state.workflow.respuesta = response.data?.message || 'No se pudieron obtener los clientes.';
      }
    } else {
      state.workflow.clientes_encontrados = false;
      state.workflow.respuesta = response.data?.message || 'Error al obtener clientes.';
    }
  } catch (error) {
    bp.logger.error('Error fetching clientes:', error.message);
    state.workflow.clientes_encontrados = false;
    state.workflow.respuesta = `Error al conectar con el servidor: ${error.message}`;
  }
};

module.exports = { handler, translations: {} };