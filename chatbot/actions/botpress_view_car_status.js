const axios = require('axios');

module.exports = async (bp, state, event, params) => {
  try {
    const apiBaseUrl = process.env.BOTPRESS_API_URL || 'http://127.0.0.1:8888/chatbot';
    const matricula = state.workflow.matricula || params.matricula || '';
    const email = state.workflow.email || params.email || '';

    if (!matricula && !email) {
      state.workflow.respuesta = 'Debes proporcionar la matrícula o el email registrado para consultar el estado del coche.';
      state.workflow.car_status = null;
      return { state };
    }

    const query = matricula ? `matricula=${encodeURIComponent(matricula)}` : `email=${encodeURIComponent(email)}`;
    const url = `${apiBaseUrl}/gestionar_citas.php?action=ver_estado_coche&${query}`;
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