document.addEventListener('DOMContentLoaded', () => {
    const tableBody = document.getElementById('reparacionesTableBody');
    const formReparacion = document.getElementById('formReparacion');
    const modalInstance = new bootstrap.Modal(document.getElementById('reparacionModal'));
    const btnDeleteRep = document.getElementById('btnDeleteRep');

    window.cargarReparaciones = async function() {
        if (!tableBody) return;
        tableBody.innerHTML = '<tr><td colspan="6" class="text-center p-4">Cargando reparaciones...</td></tr>';

        try {
            const res = await fetch('/api/reparaciones', { credentials: 'include' });
            if (res.ok) {
                const data = await res.json();
                tableBody.innerHTML = '';
                
                if (data.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="6" class="text-center p-4 text-muted">No hay reparaciones registradas.</td></tr>';
                    return;
                }

                data.forEach(rep => {
                    // Badge Estado Presupuesto
                    let badgePresupuesto = 'bg-warning text-dark'; // Pendiente
                    if (rep.estado_presupuesto === 'Aprobado') badgePresupuesto = 'bg-success';
                    if (rep.estado_presupuesto === 'No Aprobado') badgePresupuesto = 'bg-danger';

                    // Badge Estado Reparación
                    let badgeEstado = 'bg-info text-dark'; // En Proceso
                    if (rep.estado === 'Esperando Piezas') badgeEstado = 'bg-warning text-dark';
                    if (rep.estado === 'Finalizada') badgeEstado = 'bg-success';

                    tableBody.innerHTML += `
                        <tr>
                            <td class="ps-4 fw-bold text-start">#${rep.id_reparacion}</td>
                            <td class="text-start">
                                <div class="fw-bold">${rep.modelo_auto}</div>
                                <div class="small text-muted">${rep.matricula}</div>
                            </td>
                            <td class="text-start text-truncate" style="max-width: 200px;" title="${rep.descripcion_motivo}">${rep.descripcion_motivo}</td>
                            <td><span class="badge ${badgePresupuesto} p-2">${rep.estado_presupuesto}</span></td>
                            <td><span class="badge ${badgeEstado} p-2">${rep.estado}</span></td>
                            <td class="text-end pe-4">
                                <button class="btn btn-sm btn-outline-success rounded-circle shadow-sm me-1"
                                        onclick="abrirModalPresupuesto(${rep.id_reparacion})"
                                        title="Generar Presupuesto PDF">
                                    <i class="fas fa-file-pdf"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-primary rounded-circle shadow-sm" 
                                        onclick="abrirModalReparacion(${rep.id_reparacion}, '${rep.modelo_auto.replace(/'/g, "\\'")}', '${rep.matricula.replace(/'/g, "\\'")}', '${rep.descripcion_motivo.replace(/'/g, "\\'")}', '${rep.estado_presupuesto}', '${rep.estado}')"
                                        title="Editar">
                                    <i class="fas fa-pencil-alt"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
            }
        } catch (e) {
            console.error('Error cargando reparaciones:', e);
            tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-danger p-4">Error de conexión al cargar reparaciones.</td></tr>';
        }
    };

    const repClienteSelect = document.getElementById('repCliente');
    const repVehiculoSelect = document.getElementById('repVehiculo');
    const repMatriculaVal = document.getElementById('repMatriculaVal');
    const repModeloVal = document.getElementById('repModeloVal');

    async function loadClientesForRep() {
        if (!repClienteSelect) return;
        try {
            const res = await fetch('/api/clientes', { credentials: 'include' });
            if (res.ok) {
                const clientes = await res.json();
                repClienteSelect.innerHTML = '<option value="">Seleccione un cliente</option>';
                clientes.forEach(cli => {
                    const option = document.createElement('option');
                    option.value = cli.id_cliente;
                    option.textContent = cli.nombre_completo;
                    repClienteSelect.appendChild(option);
                });
            }
        } catch (e) {
            console.error(e);
        }
    }

    async function loadVehiculosForRep(id_cliente) {
        if (!repVehiculoSelect) return;
        repVehiculoSelect.innerHTML = '<option value="">Cargando...</option>';
        repVehiculoSelect.disabled = true;

        if (!id_cliente) {
            repVehiculoSelect.innerHTML = '<option value="">Seleccione primero un cliente</option>';
            return;
        }

        try {
            const res = await fetch(`/api/vehiculos?id_cliente=${id_cliente}`, { credentials: 'include' });
            if (res.ok) {
                const vehiculos = await res.json();
                if (vehiculos.length === 0) {
                    repVehiculoSelect.innerHTML = '<option value="">Este cliente no tiene vehículos</option>';
                    return;
                }
                
                repVehiculoSelect.innerHTML = '<option value="">Seleccione un vehículo</option>';
                vehiculos.forEach(veh => {
                    const option = document.createElement('option');
                    option.value = veh.matricula;
                    option.dataset.modelo = veh.modelo;
                    option.textContent = `${veh.matricula} - ${veh.modelo} ${veh.marca || ''}`;
                    repVehiculoSelect.appendChild(option);
                });
                repVehiculoSelect.disabled = false;
            }
        } catch (e) {
            console.error(e);
        }
    }

    if (repClienteSelect) {
        repClienteSelect.addEventListener('change', (e) => {
            loadVehiculosForRep(e.target.value);
        });
    }

    if (repVehiculoSelect) {
        repVehiculoSelect.addEventListener('change', (e) => {
            const selectedOption = e.target.options[e.target.selectedIndex];
            if (selectedOption && selectedOption.value) {
                repMatriculaVal.value = selectedOption.value;
                repModeloVal.value = selectedOption.dataset.modelo;
            } else {
                repMatriculaVal.value = '';
                repModeloVal.value = '';
            }
        });
    }

    // Cargar clientes automáticamente cuando el modal se abre vía data-bs-toggle
    document.getElementById('reparacionModal')?.addEventListener('show.bs.modal', async () => {
        if (!document.getElementById('repId').value) {
            await loadClientesForRep();
            if (repVehiculoSelect) {
                repVehiculoSelect.innerHTML = '<option value="">Seleccione primero un cliente</option>';
                repVehiculoSelect.disabled = true;
            }
            btnDeleteRep.classList.add('d-none');
            repMatriculaVal.value = '';
            repModeloVal.value = '';
        }
    });

    window.abrirModalReparacion = async function(id = '', modelo = '', matricula = '', descripcion = '', estadoPres = 'Pendiente', estadoRep = 'En Proceso') {
        document.getElementById('repId').value = id;
        document.getElementById('repDescripcion').value = descripcion;
        document.getElementById('repEstadoPresupuesto').value = estadoPres;
        document.getElementById('repEstado').value = estadoRep;

        if (id) {
            btnDeleteRep.classList.remove('d-none');
            // En modo edición es complejo repoblar los selects sin saber el id_cliente, 
            // así que para simplificar forzamos el vehículo si ya está seteado.
            repMatriculaVal.value = matricula;
            repModeloVal.value = modelo;
            
            if (repClienteSelect) repClienteSelect.innerHTML = `<option value="">Edición (Seleccione cliente si desea cambiar vehículo)</option>`;
            if (repVehiculoSelect) {
                repVehiculoSelect.innerHTML = `<option value="${matricula}" data-modelo="${modelo}" selected>${matricula} - ${modelo} (Actual)</option>`;
                repVehiculoSelect.disabled = false;
            }
            await loadClientesForRep(); // Cargar detrás para permitir cambio
        } else {
            btnDeleteRep.classList.add('d-none');
            repMatriculaVal.value = '';
            repModeloVal.value = '';
            await loadClientesForRep();
            if (repVehiculoSelect) {
                repVehiculoSelect.innerHTML = '<option value="">Seleccione primero un cliente</option>';
                repVehiculoSelect.disabled = true;
            }
        }

        modalInstance.show();
    };

    if (formReparacion) {
        formReparacion.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const id = document.getElementById('repId').value;
            const matricula = repMatriculaVal.value;
            const modelo = repModeloVal.value;

            if (!matricula || !modelo) {
                alert("Debe seleccionar un vehículo.");
                return;
            }

            const payload = {
                modelo_auto: modelo,
                matricula: matricula,
                descripcion_motivo: document.getElementById('repDescripcion').value,
                estado_presupuesto: document.getElementById('repEstadoPresupuesto').value,
                estado: document.getElementById('repEstado').value
            };

            const method = id ? 'PUT' : 'POST';
            if (id) payload.id_reparacion = id;

            try {
                const res = await fetch('/api/reparaciones', {
                    method: method,
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify(payload)
                });

                if (res.ok) {
                    modalInstance.hide();
                    await window.cargarReparaciones();
                } else {
                    const errorData = await res.json();
                    alert("Error: " + (errorData.message || "No se pudo guardar la reparación"));
                }
            } catch (err) {
                console.error(err);
                alert("Fallo de red al intentar guardar la reparación.");
            }
        });
    }

    if (btnDeleteRep) {
        btnDeleteRep.addEventListener('click', async () => {
            const id = document.getElementById('repId').value;
            if (!id || !confirm('¿Estás seguro de que deseas eliminar esta reparación?')) return;

            try {
                const res = await fetch('/api/reparaciones', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({ id_reparacion: id })
                });

                if (res.ok) {
                    modalInstance.hide();
                    await window.cargarReparaciones();
                } else {
                    alert("Error al eliminar la reparación.");
                }
            } catch (err) {
                console.error(err);
                alert("Fallo de red al intentar eliminar la reparación.");
            }
        });
    }

    // ── Modal de Presupuesto ────────────────────────────────────────────────────
    const presModal       = new bootstrap.Modal(document.getElementById('presupuestoModal'));
    const formPresupuesto = document.getElementById('formPresupuesto');
    const presPiezas      = document.getElementById('presTotalPiezas');
    const presManoObra    = document.getElementById('presTotalManoObra');
    const presGranTotal   = document.getElementById('presGranTotal');
    const presRepId       = document.getElementById('presRepId');
    const presIdInput     = document.getElementById('presIdReparacion');

    function actualizarTotal() {
        const t = (parseFloat(presPiezas.value) || 0) + (parseFloat(presManoObra.value) || 0);
        presGranTotal.textContent = '€ ' + t.toFixed(2);
    }
    if (presPiezas)   presPiezas.addEventListener('input', actualizarTotal);
    if (presManoObra) presManoObra.addEventListener('input', actualizarTotal);

    window.abrirModalPresupuesto = function(id_reparacion) {
        presRepId.textContent   = '#' + id_reparacion;
        presIdInput.value       = id_reparacion;
        presPiezas.value        = '';
        presManoObra.value      = '';
        presGranTotal.textContent = '€ 0.00';
        presModal.show();
    };

    if (formPresupuesto) {
        formPresupuesto.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = formPresupuesto.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';

            const id_reparacion  = parseInt(presIdInput.value);
            const total_piezas   = parseFloat(presPiezas.value)    || 0;
            const total_mano_obra = parseFloat(presManoObra.value) || 0;

            try {
                const res = await fetch('/api/presupuestos', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({ id_reparacion, total_piezas, total_mano_obra })
                });

                if (res.ok || res.status === 503) {
                    // 503 puede ser "ya existe" — intentamos descargar igualmente
                    presModal.hide();
                    window.open(`/api/presupuestos?id_reparacion=${id_reparacion}`, '_blank');
                } else {
                    const data = await res.json();
                    alert('Error: ' + (data.message || 'No se pudo guardar el presupuesto.'));
                }
            } catch (err) {
                console.error(err);
                alert('Error de conexión al guardar el presupuesto.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save me-1"></i> Guardar y descargar PDF';
            }
        });
    }
});
