const axios = require('axios');

module.exports = async (bp, state, event, params) => {
  try {
    const apiBaseUrl = process.env.BOTPRESS_API_URL || 'http://127.0.0.1:8888/chatbot';
    const url = `${apiBaseUrl}/gestionar_faqs.php?action=listar`;
    const response = await axios.get(url);

    if (response.status === 200 && response.data) {
      state.workflow.faqs = response.data;
      state.workflow.respuesta = 'Aquí están las dudas frecuentes.';
    } else {
      state.workflow.faqs = [];
      state.workflow.respuesta = response.data?.message || 'No se pudieron obtener las dudas frecuentes.';
    }
  } catch (error) {
    bp.logger.error('Error get_faqs:', error.message);
    state.workflow.faqs = [];
    state.workflow.respuesta = `Error al cargar dudas frecuentes: ${error.message}`;
  }
  return { state };
};