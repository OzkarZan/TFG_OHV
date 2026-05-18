module.exports = async (bp, state, event, params) => {
  state.workflow.workshop_contact = {
    telefono: params.telefono || '+34 600 123 456',
    email: params.email || 'contacto@autosynctfg.site',
    mensaje: params.mensaje || 'Un asesor del taller te contactará pronto.'
  };
  state.workflow.respuesta = `El taller te puede contactar por teléfono ${state.workflow.workshop_contact.telefono} o email ${state.workflow.workshop_contact.email}.`;
  return { state };
};