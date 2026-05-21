document.addEventListener('DOMContentLoaded', async () => {
    // 1. Verificar sesión y mostrar nombre en navbar
    try {
        const authRes = await fetch('/api/auth/me', { credentials: 'include' });
        if (authRes.ok) {
            const userData = await authRes.json();
            document.getElementById('dashboardUserName').innerText = userData.nombre_completo;
            document.getElementById('dashboardAvatar').src = `https://ui-avatars.com/api/?name=${encodeURIComponent(userData.nombre_completo)}&background=0055d4&color=fff`;

            //Redirigir si no es empleado/admin
            if (userData.rol === 'cliente') {
                window.location.href = 'client.html';
            }
        } else {
            window.location.href = 'index.html';
        }
    } catch (e) {
        console.error(e);
        window.location.href = 'index.html';
    }

    // 3. Configurar FullCalendar
    const calendarEl = document.getElementById('calendar');
    const citaModal = new bootstrap.Modal(document.getElementById('citaModal'));
    const formCita = document.getElementById('formCita');
    const btnDeleteCita = document.getElementById('btnDeleteCita');

    window.calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'es',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events: fetchCitas,
        dateClick: function (info) {
            openCitaModal({ fecha_hora: info.dateStr + 'T09:00' });
        },
        eventClick: function (info) {
            openCitaModal({
                id_cita: info.event.id,
                fecha_hora: info.event.startStr.slice(0, 16),
                motivo: info.event.extendedProps.motivo,
                estado: info.event.extendedProps.estado,
                prioridad: info.event.extendedProps.prioridad,
                id_cliente: info.event.extendedProps.id_cliente,
                id_vehiculo: info.event.extendedProps.id_vehiculo
            });
        }
    });

    async function fetchCitas(fetchInfo, successCallback, failureCallback) {
        try {
            const res = await fetch('/api/citas', { credentials: 'include' });
            const data = await res.json();

            const events = data.map(cita => {
                let color = '#0055d4'; // Media
                if (cita.prioridad === 'Alta') color = '#dc3545';
                if (cita.prioridad === 'Baja') color = '#198754';
                if (cita.estado === 'Cancelada') color = '#6c757d';

                return {
                    id: cita.id_cita,
                    title: cita.motivo,
                    start: cita.fecha_hora,
                    color: color,
                    extendedProps: {
                        motivo: cita.motivo,
                        estado: cita.estado,
                        prioridad: cita.prioridad,
                        id_cliente: cita.id_cliente,
                        id_vehiculo: cita.id_vehiculo,
                        id_mecanico: cita.id_mecanico
                    }
                };
            });
            successCallback(events);
        } catch (e) {
            failureCallback(e);
        }
    }

    // ──  Mecánicos ──

    window.cargarMecanicosParaCita = async function (selectedId = null) {
        const select = document.getElementById('citaMecanico');
        if (!select) return;
        try {
            const res = await fetch('/api/mecanicos', { credentials: 'include' });
            if (!res.ok) return;
            const mecanicos = await res.json();
            select.innerHTML = '<option value="">Sin mecánico asignado</option>';
            mecanicos.forEach(m => {
                const opt = document.createElement('option');
                opt.value = m.id_mecanico;
                opt.textContent = m.nombre_completo + (m.especialidad ? ` (${m.especialidad})` : '');
                if (selectedId && m.id_mecanico == selectedId) opt.selected = true;
                select.appendChild(opt);
            });
        } catch (e) {
            console.error('Error cargando mecánicos para cita', e);
        }
    };

    async function checkDisponibilidad() {
        const id_mecanico = document.getElementById('citaMecanico')?.value;
        const fecha_hora = document.getElementById('citaFechaHora')?.value;
        const indicator = document.getElementById('citaDisponibilidad');
        if (!indicator) return;

        if (!id_mecanico || !fecha_hora) {
            indicator.innerHTML = '';
            return;
        }

        indicator.innerHTML = '<span class="text-muted">Comprobando disponibilidad…</span>';
        try {
            const res = await fetch(`/api/mecanicos?action=disponibilidad&id_mecanico=${id_mecanico}&fecha_hora=${encodeURIComponent(fecha_hora)}`, { credentials: 'include' });
            const data = await res.json();
            if (data.disponible) {
                indicator.innerHTML = '<span class="text-success"><i class="fas fa-check-circle me-1"></i>Mecánico disponible</span>';
            } else {
                indicator.innerHTML = `<span class="text-danger"><i class="fas fa-times-circle me-1"></i>${data.motivo}</span>`;
            }
        } catch (e) {
            indicator.innerHTML = '';
        }
    }

    document.getElementById('citaMecanico')?.addEventListener('change', checkDisponibilidad);
    document.getElementById('citaFechaHora')?.addEventListener('change', checkDisponibilidad);

    async function loadClientesForSelect(selectedId = null) {
        const select = document.getElementById('citaCliente');
        if (!select) return;

        try {
            const res = await fetch('/api/clientes', { credentials: 'include' });
            if (res.ok) {
                const clientes = await res.json();
                select.innerHTML = '<option value="">Seleccione un cliente (opcional)</option>';
                clientes.forEach(cli => {
                    const option = document.createElement('option');
                    option.value = cli.id_cliente;
                    option.textContent = cli.nombre_completo + (cli.telefono ? ` - ${cli.telefono}` : '');
                    if (selectedId && cli.id_cliente == selectedId) {
                        option.selected = true;
                    }
                    select.appendChild(option);
                });
            }
        } catch (e) {
            console.error("Error cargando clientes para el select", e);
        }
    }

    function openCitaModal(cita = {}) {
        document.getElementById('citaId').value = cita.id_cita || '';
        document.getElementById('citaFechaHora').value = cita.fecha_hora || '';
        document.getElementById('citaMotivo').value = cita.motivo || '';
        document.getElementById('citaEstado').value = cita.estado || 'Pendiente';
        document.getElementById('citaPrioridad').value = cita.prioridad || 'Media';
        document.getElementById('citaDisponibilidad').innerHTML = '';

        cargarMecanicosParaCita(cita.id_mecanico || null);
        loadClientesForSelect(cita.id_cliente);
        if (cita.id_cliente) {
            loadVehiculosForSelect(cita.id_cliente, cita.id_vehiculo);
        } else {
            document.getElementById('citaVehiculo').innerHTML = '<option value="">Seleccione primero un cliente</option>';
            document.getElementById('citaVehiculo').disabled = true;
        }
        if (cita.id_cita) {
            btnDeleteCita.classList.remove('d-none');
        } else {
            btnDeleteCita.classList.add('d-none');
        }

        citaModal.show();
    }

    const citaClienteSelect = document.getElementById('citaCliente');
    const citaVehiculoSelect = document.getElementById('citaVehiculo');

    // Cargar clientes y mecánicos siempre que el modal se abra (cubre botones con data-bs-toggle)
    document.getElementById('citaModal')?.addEventListener('show.bs.modal', () => {
        if (!document.getElementById('citaId').value) {
            loadClientesForSelect();
            cargarMecanicosParaCita();
            document.getElementById('citaVehiculo').innerHTML = '<option value="">Seleccione primero un cliente</option>';
            document.getElementById('citaVehiculo').disabled = true;
            document.getElementById('citaFechaHora').value = '';
            document.getElementById('citaMotivo').value = '';
            document.getElementById('citaEstado').value = 'Pendiente';
            document.getElementById('citaPrioridad').value = 'Media';
            document.getElementById('citaDisponibilidad').innerHTML = '';
            btnDeleteCita.classList.add('d-none');
        }
    });

    async function loadVehiculosForSelect(id_cliente, selectedId = null) {
        if (!citaVehiculoSelect) return;
        citaVehiculoSelect.innerHTML = '<option value="">Cargando...</option>';
        citaVehiculoSelect.disabled = true;

        if (!id_cliente) {
            citaVehiculoSelect.innerHTML = '<option value="">Seleccione primero un cliente</option>';
            return;
        }

        try {
            const res = await fetch(`/api/vehiculos?id_cliente=${id_cliente}`, { credentials: 'include' });
            if (res.ok) {
                const vehiculos = await res.json();
                if (vehiculos.length === 0) {
                    citaVehiculoSelect.innerHTML = '<option value="">Este cliente no tiene vehículos</option>';
                    return;
                }

                if (vehiculos.length > 1) {
                    citaVehiculoSelect.innerHTML = '<option value="">Seleccione un vehículo</option>';
                } else {
                    citaVehiculoSelect.innerHTML = '';
                }
                vehiculos.forEach(veh => {
                    const option = document.createElement('option');
                    option.value = veh.id_vehiculo;
                    option.textContent = `${veh.matricula} - ${veh.modelo} ${veh.marca || ''}`;
                    if ((selectedId && veh.id_vehiculo == selectedId) || vehiculos.length === 1) {
                        option.selected = true;
                    }
                    citaVehiculoSelect.appendChild(option);
                });
                citaVehiculoSelect.disabled = false;
            }
        } catch (e) {
            console.error("Error cargando vehículos", e);
            citaVehiculoSelect.innerHTML = '<option value="">Error al cargar</option>';
        }
    }

    if (citaClienteSelect) {
        citaClienteSelect.addEventListener('change', (e) => {
            loadVehiculosForSelect(e.target.value);
        });
    }

    formCita.addEventListener('submit', async (e) => {
        e.preventDefault();

        const id_cita = document.getElementById('citaId').value;
        const id_vehiculo = citaVehiculoSelect.value;

        if (!id_vehiculo) {
            alert("Debe seleccionar un vehículo para la cita.");
            return;
        }

        const mecId = document.getElementById('citaMecanico')?.value || null;
        const payload = {
            fecha_hora: document.getElementById('citaFechaHora').value,
            motivo: document.getElementById('citaMotivo').value,
            estado: document.getElementById('citaEstado').value,
            prioridad: document.getElementById('citaPrioridad').value,
            id_cliente: citaClienteSelect.value,
            id_vehiculo: id_vehiculo,
            id_mecanico: mecId || null
        };

        const method = id_cita ? 'PUT' : 'POST';
        if (id_cita) payload.id_cita = id_cita;

        try {
            const res = await fetch('/api/citas', {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify(payload)
            });

            if (res.ok) {
                citaModal.hide();
                window.calendar.refetchEvents();
            } else {
                alert("Error al guardar cita");
            }
        } catch (err) {
            console.error(err);
        }
    });

    btnDeleteCita.addEventListener('click', async () => {
        const id_cita = document.getElementById('citaId').value;
        if (!id_cita || !confirm('¿Eliminar cita?')) return;

        try {
            const res = await fetch('/api/citas', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ id_cita: id_cita })
            });

            if (res.ok) {
                citaModal.hide();
                window.calendar.refetchEvents();
            } else {
                alert("Error al eliminar cita");
            }
        } catch (err) {
            console.error(err);
        }
    });

});
