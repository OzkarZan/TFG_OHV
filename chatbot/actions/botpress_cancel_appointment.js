const axios = require('axios');

module.exports = async (bp, state, event, params) => {
  try {
    const apiBaseUrl = process.env.BOTPRESS_API_URL || 'http://127.0.0.1:8888/chatbot';
    const id_cita = state.workflow.id_cita || params.id_cita || null;

    if (!id_cita) {
      state.workflow.respuesta = 'Necesito el número de cita para poder cancelarla.';
      return { state };
    }

    const url = `${apiBaseUrl}/gestionar_citas.php?action=cancelar`;
    const response = await axios.delete(url, { data: { id_cita } });

    if (response.status === 200 && response.data) {
      state.workflow.cita_cancelada = true;
      state.workflow.respuesta = response.data.message || 'Cita cancelada correctamente.';
    } else {
      state.workflow.cita_cancelada = false;
      state.workflow.respuesta = response.data?.message || 'No se pudo cancelar la cita.';
    }
  } catch (error) {
    bp.logger.error('Error cancel_appointment:', error.message);
    state.workflow.cita_cancelada = false;
    state.workflow.respuesta = `Error al cancelar la cita: ${error.message}`;
  }
  return { state };
};