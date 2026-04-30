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
                                        onclick="crearYDescargarPresupuesto(${rep.id_reparacion})"
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

    window.abrirModalReparacion = function(id = '', modelo = '', matricula = '', descripcion = '', estadoPres = 'Pendiente', estadoRep = 'En Proceso') {
        document.getElementById('repId').value = id;
        document.getElementById('repModelo').value = modelo;
        document.getElementById('repMatricula').value = matricula;
        document.getElementById('repDescripcion').value = descripcion;
        document.getElementById('repEstadoPresupuesto').value = estadoPres;
        document.getElementById('repEstado').value = estadoRep;

        if (id) {
            btnDeleteRep.classList.remove('d-none');
        } else {
            btnDeleteRep.classList.add('d-none');
        }

        modalInstance.show();
    };

    if (formReparacion) {
        formReparacion.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const id = document.getElementById('repId').value;
            const payload = {
                modelo_auto: document.getElementById('repModelo').value,
                matricula: document.getElementById('repMatricula').value,
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

    window.crearYDescargarPresupuesto = async function(id_reparacion) {
        try {
            // Generar Presupuesto si no existe (Demo Data: 100 y 50)
            const payload = {
                id_reparacion: id_reparacion,
                total_piezas: Math.floor(Math.random() * 500) + 50,
                total_mano_obra: Math.floor(Math.random() * 300) + 50
            };
            
            await fetch('/api/presupuestos', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify(payload)
            });

            // Descargar el PDF generado
            window.open(`/api/presupuestos/generar_pdf?id_reparacion=${id_reparacion}`, '_blank');
        } catch(e) {
            console.error('Error generando presupuesto:', e);
            alert('Error al generar el PDF del presupuesto.');
        }
    };
});
