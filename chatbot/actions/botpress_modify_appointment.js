const axios = require('axios');

module.exports = async (bp, state, event, params) => {
  try {
    const apiBaseUrl = process.env.BOTPRESS_API_URL || 'http://127.0.0.1:8888/chatbot';
    const id_cita = state.workflow.id_cita || params.id_cita || null;
    const fecha_hora = state.workflow.fecha_hora || params.fecha_hora || null;
    const motivo = state.workflow.motivo || params.motivo || null;
    const estado = state.workflow.estado || params.estado || null;

    if (!id_cita) {
      state.workflow.respuesta = 'Necesito el número de cita para modificarla.';
      return { state };
    }

    const url = `${apiBaseUrl}/gestionar_citas.php?action=modificar`;
    const response = await axios.put(url, {
      id_cita,
      fecha_hora,
      motivo,
      estado
    });

    if (response.status === 200 && response.data) {
      state.workflow.respuesta = response.data.message || 'Cita modificada correctamente.';
      state.workflow.cita_modificada = true;
    } else {
      state.workflow.cita_modificada = false;
      state.workflow.respuesta = response.data?.message || 'No se pudo modificar la cita.';
    }
  } catch (error) {
    bp.logger.error('Error modify_appointment:', error.message);
    state.workflow.cita_modificada = false;
    state.workflow.respuesta = `Error al modificar la cita: ${error.message}`;
  }
  return { state };
};