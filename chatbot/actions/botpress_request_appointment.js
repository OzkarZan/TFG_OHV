const axios = require('axios');

module.exports = async (bp, state, event, params) => {
  try {
    // Cambia esta URL según donde esté corriendo tu backend
    const apiBaseUrl = process.env.BOTPRESS_API_URL || 'http://127.0.0.1:8888/chatbot';
    const email = state.workflow.email || params.email || '';
    const matricula = state.workflow.matricula || params.matricula || '';
    const fecha_hora = state.workflow.fecha_hora || params.fecha_hora || '';
    const motivo = state.workflow.motivo || params.motivo || 'Revisión general';
    const marca = state.workflow.marca || params.marca || '';
    const modelo = state.workflow.modelo || params.modelo || '';

    // DEBUG: Agrega logs para ver qué valores llegan
    bp.logger.info('Email:', email);
    bp.logger.info('Matrícula:', matricula);
    bp.logger.info('Fecha:', fecha_hora);
    bp.logger.info('Motivo:', motivo);

    if (!email || !matricula || !fecha_hora) {
      state.workflow.respuesta = 'Necesito tu email, matrícula y fecha para reservar la cita.';
      return { state };
    }

    const url = `${apiBaseUrl}/gestionar_citas.php?action=reservar`;
    bp.logger.info('URL:', url);

    const response = await axios.post(url, {
      email,
      matricula,
      fecha_hora,
      motivo,
      marca,
      modelo
    });

    bp.logger.info('Response status:', response.status);
    bp.logger.info('Response data:', response.data);

    if (response.status === 201 && response.data) {
      state.workflow.cita = response.data;
      state.workflow.respuesta = `Cita reservada correctamente. Tu número es ${response.data.id_cita}.`;
    } else {
      state.workflow.cita = null;
      state.workflow.respuesta = response.data?.message || 'No se pudo reservar la cita.';
    }
  } catch (error) {
    bp.logger.error('Error request_appointment:', error.message);
    state.workflow.cita = null;
    state.workflow.respuesta = `Error al reservar la cita: ${error.message}`;
  }
  return { state };
};