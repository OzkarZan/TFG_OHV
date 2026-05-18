const axios = require('axios');

module.exports = async (bp, state, event, params) => {
  try {
    // Cambia esta URL según donde esté corriendo tu backend
    const apiBaseUrl = process.env.BOTPRESS_API_URL || 'http://127.0.0.1:8888/chatbot';
    const matricula = state.workflow.matricula || params.matricula || '';

    if (!matricula) {
      state.workflow.respuesta = 'Necesito la matrícula del vehículo para consultar el estado.';
      return { state };
    }

    const url = `${apiBaseUrl}/gestionar_citas.php?action=ver_estado_coche&matricula=${encodeURIComponent(matricula)}`;
    bp.logger.info('Consultando estado coche:', url);

    const response = await axios.get(url);

    if (response.status === 200 && response.data) {
      state.workflow.car_status = response.data;
      state.workflow.respuesta = 'Aquí está el estado actual de tu coche.';
    } else {
      state.workflow.car_status = null;
      state.workflow.respuesta = response.data?.message || 'No se pudo obtener el estado del coche.';
    }
  } catch (error) {
    bp.logger.error('Error view_car_status:', error.message);
    state.workflow.car_status = null;
    state.workflow.respuesta = `Error al conectar con el servidor: ${error.message}`;
  }
  return { state };
};