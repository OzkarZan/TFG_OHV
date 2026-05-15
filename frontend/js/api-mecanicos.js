document.addEventListener('DOMContentLoaded', () => {

    const mecanicoModal = document.getElementById('mecanicoModal')
        ? new bootstrap.Modal(document.getElementById('mecanicoModal'))
        : null;

    const DIAS = ['', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
    let currentMecanicoId = null;

    // ── Carga la tabla de mecánicos ──

    window.cargarMecanicos = async function () {
        const tbody = document.getElementById('mecanicosTableBody');
        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-4">Cargando...</td></tr>';

        try {
            const [mecRes, citasRes] = await Promise.all([
                fetch('/api/mecanicos', { credentials: 'include' }),
                fetch('/api/citas',     { credentials: 'include' })
            ]);

            const mecanicos = await mecRes.json();
            const citas     = await citasRes.json();

            if (!mecanicos.length) {
                tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-4">No hay mecánicos registrados.</td></tr>';
                return;
            }

            // Count this-week citas per mechanic
            const now       = new Date();
            const weekStart = new Date(now);
            weekStart.setDate(now.getDate() - ((now.getDay() + 6) % 7)); // Monday
            weekStart.setHours(0, 0, 0, 0);
            const weekEnd = new Date(weekStart);
            weekEnd.setDate(weekStart.getDate() + 7);

            const citaCount = {};
            citas.forEach(c => {
                if (!c.id_mecanico) return;
                const d = new Date(c.fecha_hora);
                if (d >= weekStart && d < weekEnd) {
                    citaCount[c.id_mecanico] = (citaCount[c.id_mecanico] || 0) + 1;
                }
            });

            tbody.innerHTML = mecanicos.map(m => `
                <tr style="cursor:pointer" onclick="seleccionarMecanico(${m.id_mecanico}, '${m.nombre_completo.replace(/'/g, "\\'")}')">
                    <td class="ps-4 fw-bold text-dark">${m.nombre_completo}</td>
                    <td class="text-muted small">${m.especialidad || '—'}</td>
                    <td>
                        <span class="badge ${citaCount[m.id_mecanico] ? 'bg-primary' : 'bg-secondary'}">
                            ${citaCount[m.id_mecanico] || 0}
                        </span>
                    </td>
                </tr>
            `).join('');
        } catch (e) {
            console.error('Error cargando mecánicos', e);
            tbody.innerHTML = '<tr><td colspan="3" class="text-center text-danger py-4">Error al cargar.</td></tr>';
        }
    };

    // ── Selecciona un mecánico y carga su panel de detalle ──

    window.seleccionarMecanico = async function (id, nombre) {
        currentMecanicoId = id;
        document.getElementById('mecDetailNombre').textContent = nombre;
        document.getElementById('mecanicoDetailPanel').style.display = '';
        await Promise.all([cargarHorario(id), cargarDetalle(id)]);
    };

    // ── Horario semanal ──

    async function cargarHorario(id_mecanico) {
        const editor = document.getElementById('horarioEditor');
        if (!editor) return;

        editor.innerHTML = '<p class="text-muted small">Cargando horario...</p>';

        try {
            const res = await fetch(`/api/mecanico-horarios?id_mecanico=${id_mecanico}`, { credentials: 'include' });
            const horarios = await res.json();

            const map = {};
            horarios.forEach(h => { map[h.dia_semana] = h; });

            const rows = [1, 2, 3, 4, 5, 6, 7].map(dia => {
                const h = map[dia];
                const btnDel = h
                    ? `<button class="btn btn-sm btn-outline-danger ms-1" onclick="eliminarHorario(${id_mecanico},${dia})" title="Quitar"><i class="fas fa-times"></i></button>`
                    : '';
                return `
                    <tr>
                        <td class="fw-bold small ps-2">${DIAS[dia]}</td>
                        <td><input type="time" class="form-control form-control-sm" id="hi_${dia}" value="${h ? h.hora_inicio.slice(0, 5) : ''}"></td>
                        <td><input type="time" class="form-control form-control-sm" id="hf_${dia}" value="${h ? h.hora_fin.slice(0, 5) : ''}"></td>
                        <td class="text-end pe-2">
                            <button class="btn btn-sm btn-outline-primary" onclick="guardarHorario(${id_mecanico},${dia})" title="Guardar"><i class="fas fa-save"></i></button>
                            ${btnDel}
                        </td>
                    </tr>`;
            }).join('');

            editor.innerHTML = `
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr><th class="ps-2">Día</th><th>Inicio</th><th>Fin</th><th></th></tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>`;
        } catch (e) {
            editor.innerHTML = '<p class="text-danger small">Error al cargar horario.</p>';
        }
    }

    window.guardarHorario = async function (id_mecanico, dia_semana) {
        const inicio = document.getElementById(`hi_${dia_semana}`)?.value;
        const fin    = document.getElementById(`hf_${dia_semana}`)?.value;

        if (!inicio || !fin) { alert('Introduce ambas horas.'); return; }
        if (inicio >= fin)   { alert('La hora de inicio debe ser anterior a la de fin.'); return; }

        const res = await fetch('/api/mecanico-horarios', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ id_mecanico, dia_semana, hora_inicio: inicio, hora_fin: fin })
        });

        if (res.ok) {
            await cargarHorario(id_mecanico);
        } else {
            alert('Error al guardar horario.');
        }
    };

    window.eliminarHorario = async function (id_mecanico, dia_semana) {
        if (!confirm(`¿Eliminar horario del ${DIAS[dia_semana]}?`)) return;

        const res = await fetch('/api/mecanico-horarios', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ id_mecanico, dia_semana })
        });

        if (res.ok) {
            await cargarHorario(id_mecanico);
        }
    };

    // ── Detalle: citas esta semana + presupuestos ──

    async function cargarDetalle(id_mecanico) {
        try {
            const res  = await fetch(`/api/mecanicos?action=detalle&id_mecanico=${id_mecanico}`, { credentials: 'include' });
            const data = await res.json();

            // Citas
            const citasList = document.getElementById('mecCitasList');
            if (data.citas && data.citas.length) {
                const filas = data.citas.map(c => {
                    const fecha = new Date(c.fecha_hora).toLocaleString('es-ES', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
                    const badge = c.estado === 'Confirmada' ? 'bg-success' : c.estado === 'Cancelada' ? 'bg-secondary' : 'bg-warning text-dark';
                    return `<tr>
                        <td class="small">${fecha}</td>
                        <td class="small fw-bold">${c.matricula || '—'} <span class="text-muted">${c.modelo || ''}</span></td>
                        <td class="small text-truncate" style="max-width:140px">${c.motivo || ''}</td>
                        <td><span class="badge ${badge}">${c.estado}</span></td>
                    </tr>`;
                }).join('');
                citasList.innerHTML = `<div class="table-responsive"><table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>Fecha</th><th>Vehículo</th><th>Motivo</th><th>Estado</th></tr></thead>
                    <tbody>${filas}</tbody></table></div>`;
            } else {
                citasList.innerHTML = '<p class="text-muted small mb-0">Sin citas asignadas esta semana.</p>';
            }

            // Presupuestos
            const presList = document.getElementById('mecPresupuestosList');
            if (data.presupuestos && data.presupuestos.length) {
                const filas = data.presupuestos.map(p => {
                    const badge = p.estado === 'Aprobado' ? 'bg-success' : p.estado === 'Rechazado' ? 'bg-danger' : 'bg-secondary';
                    return `<tr>
                        <td>#${p.id_presupuesto}</td>
                        <td class="small">${p.matricula}</td>
                        <td class="fw-bold">€${parseFloat(p.gran_total).toFixed(2)}</td>
                        <td><span class="badge ${badge}">${p.estado}</span></td>
                    </tr>`;
                }).join('');
                presList.innerHTML = `<div class="table-responsive"><table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>ID</th><th>Vehículo</th><th>Total</th><th>Estado</th></tr></thead>
                    <tbody>${filas}</tbody></table></div>`;
            } else {
                presList.innerHTML = '<p class="text-muted small mb-0">Sin presupuestos.</p>';
            }
        } catch (e) {
            console.error('Error cargando detalle mecánico', e);
        }
    }

    // ── Crear mecánico ──

    document.getElementById('formMecanico')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = e.target.querySelector('button[type="submit"]');
        btn.disabled = true;

        const payload = {
            nombre_completo: document.getElementById('mecNombre').value,
            email:           document.getElementById('mecEmail').value,
            password:        document.getElementById('mecPassword').value,
            especialidad:    document.getElementById('mecEspecialidad').value
        };

        try {
            const res  = await fetch('/api/mecanicos', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify(payload)
            });
            const data = await res.json();

            if (res.ok) {
                mecanicoModal.hide();
                document.getElementById('formMecanico').reset();
                await cargarMecanicos();
                // Refresh mechanic selector in cita modal
                if (window.cargarMecanicosParaCita) window.cargarMecanicosParaCita();
            } else {
                alert(data.message || 'Error al crear mecánico.');
            }
        } finally {
            btn.disabled = false;
        }
    });

    // ── Eliminar mecánico ──

    document.getElementById('btnDeleteMecanico')?.addEventListener('click', async () => {
        if (!currentMecanicoId || !confirm('¿Eliminar este mecánico? Esta acción no se puede deshacer.')) return;

        const res = await fetch('/api/mecanicos', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ id_mecanico: currentMecanicoId })
        });

        if (res.ok) {
            document.getElementById('mecanicoDetailPanel').style.display = 'none';
            currentMecanicoId = null;
            await cargarMecanicos();
            if (window.cargarMecanicosParaCita) window.cargarMecanicosParaCita();
        } else {
            alert('Error al eliminar mecánico.');
        }
    });

});
