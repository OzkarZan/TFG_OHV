document.addEventListener('DOMContentLoaded', async () => {
    // 1. Verificar sesión y mostrar nombre en navbar
    try {
        const authRes = await fetch('/api/auth/me', { credentials: 'include' });
        if (authRes.ok) {
            const userData = await authRes.json();
            document.getElementById('dashboardUserName').innerText = userData.nombre_completo;
            document.getElementById('dashboardAvatar').src = `https://ui-avatars.com/api/?name=${encodeURIComponent(userData.nombre_completo)}&background=0055d4&color=fff`;
            
            // Redirect if not employee/admin
            if(userData.rol === 'cliente') {
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
        dateClick: function(info) {
            openCitaModal({ fecha_hora: info.dateStr + 'T09:00' });
        },
        eventClick: function(info) {
            openCitaModal({
                id_cita: info.event.id,
                fecha_hora: info.event.startStr.slice(0, 16),
                motivo: info.event.extendedProps.motivo,
                estado: info.event.extendedProps.estado,
                prioridad: info.event.extendedProps.prioridad
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
                        prioridad: cita.prioridad
                    }
                };
            });
            successCallback(events);
        } catch (e) {
            failureCallback(e);
        }
    }

    function openCitaModal(cita = {}) {
        document.getElementById('citaId').value = cita.id_cita || '';
        document.getElementById('citaFechaHora').value = cita.fecha_hora || '';
        document.getElementById('citaMotivo').value = cita.motivo || '';
        document.getElementById('citaEstado').value = cita.estado || 'Pendiente';
        document.getElementById('citaPrioridad').value = cita.prioridad || 'Media';
        
        if (cita.id_cita) {
            btnDeleteCita.classList.remove('d-none');
        } else {
            btnDeleteCita.classList.add('d-none');
        }
        
        citaModal.show();
    }

    formCita.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const id_cita = document.getElementById('citaId').value;
        const payload = {
            fecha_hora: document.getElementById('citaFechaHora').value,
            motivo: document.getElementById('citaMotivo').value,
            estado: document.getElementById('citaEstado').value,
            prioridad: document.getElementById('citaPrioridad').value
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
