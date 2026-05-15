document.addEventListener('DOMContentLoaded', () => {
    const tableBody = document.getElementById('clientesTableBody');
    const formCliente = document.getElementById('formCliente');
    const modalInstance = new bootstrap.Modal(document.getElementById('clienteModal'));
    const btnDeleteCli = document.getElementById('btnDeleteCli');

    // ── Cargar tabla de clientes ──────────────────────────────────────────────
    window.cargarClientes = async function() {
        if (!tableBody) return;
        tableBody.innerHTML = '<tr><td colspan="6" class="text-center p-4">Cargando clientes...</td></tr>';

        try {
            const res = await fetch('/api/clientes', { credentials: 'include' });
            if (res.ok) {
                const data = await res.json();
                tableBody.innerHTML = '';

                if (data.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="6" class="text-center p-4 text-muted">No hay clientes registrados.</td></tr>';
                    return;
                }

                data.forEach(cli => {
                    const vehiculosHtml = cli.vehiculos
                        ? cli.vehiculos.split(' | ').map(v => `<span class="badge bg-secondary me-1">${v}</span>`).join('')
                        : '<span class="text-muted fst-italic small">Sin vehículo</span>';

                    tableBody.innerHTML += `
                        <tr>
                            <td class="ps-4 fw-bold text-start text-dark">${cli.nombre_completo}</td>
                            <td class="text-start">${cli.correo}</td>
                            <td>${cli.telefono || '<span class="text-muted fst-italic">No especificado</span>'}</td>
                            <td class="text-start text-truncate" style="max-width: 120px;" title="${cli.direccion}">${cli.direccion || '<span class="text-muted fst-italic">No especificada</span>'}</td>
                            <td class="text-start">${vehiculosHtml}</td>
                            <td class="text-end pe-4">
                                <button class="btn btn-sm btn-outline-primary rounded-circle shadow-sm"
                                        onclick="abrirModalCliente(${cli.id_cliente}, '${cli.telefono || ''}', '${(cli.direccion || '').replace(/'/g, "\\'")}')"
                                        title="Editar">
                                    <i class="fas fa-pencil-alt"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
            }
        } catch (e) {
            console.error('Error cargando clientes:', e);
            tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-danger p-4">Error de conexión al cargar clientes.</td></tr>';
        }
    };

    // ── Cargar vehículos dentro del modal de edición ──────────────────────────
    async function cargarVehiculosEnModal(id_cliente) {
        const list   = document.getElementById('cliVehiculosList');
        const btnAdd = document.getElementById('btnMostrarAddVehiculo');
        if (!list) return;

        list.innerHTML = '<p class="text-muted small text-center py-2">Cargando vehículos...</p>';

        try {
            const res = await fetch(`/api/vehiculos?id_cliente=${id_cliente}`, { credentials: 'include' });
            if (!res.ok) { list.innerHTML = ''; return; }
            const vehiculos = await res.json();
            list.innerHTML = '';

            if (vehiculos.length === 0) {
                list.innerHTML = '<p class="text-muted small fst-italic mb-1">Sin vehículos registrados.</p>';
            } else {
                vehiculos.forEach(veh => {
                    const marcaAnio = [veh.marca, veh.anio ? `(${veh.anio})` : ''].filter(Boolean).join(' ');
                    list.innerHTML += `
                        <div class="border rounded p-2 mb-2 bg-white" id="vehCard-${veh.id_vehiculo}">
                            <div id="vehDisplay-${veh.id_vehiculo}" class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="fw-bold small">${veh.matricula}</span>
                                    <span class="text-muted small ms-1">— ${veh.modelo}${marcaAnio ? ' ' + marcaAnio : ''}</span>
                                </div>
                                <div class="d-flex gap-1">
                                    <button class="btn btn-outline-primary" style="width:28px;height:28px;padding:0;font-size:0.65rem;"
                                            onclick="toggleEditarVehiculo(${veh.id_vehiculo})" title="Editar vehículo">
                                        <i class="fas fa-pencil-alt"></i>
                                    </button>
                                    <button class="btn btn-outline-danger" style="width:28px;height:28px;padding:0;font-size:0.65rem;"
                                            onclick="eliminarVehiculo(${veh.id_vehiculo}, ${id_cliente})" title="Eliminar vehículo">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <div id="vehEdit-${veh.id_vehiculo}" class="d-none mt-2">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <input type="text" id="editVehMatricula-${veh.id_vehiculo}" class="form-control form-control-sm" value="${veh.matricula}" placeholder="Matrícula *">
                                    </div>
                                    <div class="col-6">
                                        <input type="text" id="editVehModelo-${veh.id_vehiculo}" class="form-control form-control-sm" value="${veh.modelo}" placeholder="Modelo *">
                                    </div>
                                    <div class="col-6">
                                        <input type="text" id="editVehMarca-${veh.id_vehiculo}" class="form-control form-control-sm" value="${veh.marca || ''}" placeholder="Marca">
                                    </div>
                                    <div class="col-6">
                                        <input type="number" id="editVehAnio-${veh.id_vehiculo}" class="form-control form-control-sm" value="${veh.anio || ''}" placeholder="Año">
                                    </div>
                                </div>
                                <div class="d-flex gap-2 mt-2">
                                    <button class="btn btn-sm btn-success flex-grow-1" onclick="guardarVehiculo(${veh.id_vehiculo}, ${id_cliente})">Guardar</button>
                                    <button class="btn btn-sm btn-secondary" onclick="toggleEditarVehiculo(${veh.id_vehiculo})">Cancelar</button>
                                </div>
                            </div>
                        </div>
                    `;
                });
            }

            if (btnAdd) btnAdd.classList.remove('d-none');
        } catch (e) {
            list.innerHTML = '<p class="text-danger small">Error al cargar vehículos.</p>';
        }
    }

    // ── Funciones globales para botones inline de vehículos ───────────────────

    window.toggleEditarVehiculo = function(id_vehiculo) {
        document.getElementById(`vehDisplay-${id_vehiculo}`)?.classList.toggle('d-none');
        document.getElementById(`vehEdit-${id_vehiculo}`)?.classList.toggle('d-none');
    };

    window.guardarVehiculo = async function(id_vehiculo, id_cliente) {
        const matricula = document.getElementById(`editVehMatricula-${id_vehiculo}`)?.value.trim();
        const modelo    = document.getElementById(`editVehModelo-${id_vehiculo}`)?.value.trim();
        const marca     = document.getElementById(`editVehMarca-${id_vehiculo}`)?.value.trim();
        const anio      = document.getElementById(`editVehAnio-${id_vehiculo}`)?.value;

        if (!matricula || !modelo) { alert('Matrícula y modelo son obligatorios.'); return; }

        try {
            const res = await fetch('/api/vehiculos', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ id_vehiculo, matricula, modelo, marca: marca || null, anio: anio || null })
            });

            if (res.ok) {
                await cargarVehiculosEnModal(id_cliente);
                await window.cargarClientes();
            } else {
                const data = await res.json();
                alert('Error: ' + (data.message || 'No se pudo actualizar el vehículo.'));
            }
        } catch (e) {
            alert('Error de conexión al actualizar el vehículo.');
        }
    };

    window.eliminarVehiculo = async function(id_vehiculo, id_cliente) {
        if (!confirm('¿Seguro que deseas eliminar este vehículo? Esta acción es irreversible.')) return;

        try {
            const res = await fetch('/api/vehiculos', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ id_vehiculo })
            });

            if (res.ok) {
                await cargarVehiculosEnModal(id_cliente);
                await window.cargarClientes();
            } else {
                alert('Error al eliminar el vehículo.');
            }
        } catch (e) {
            alert('Error de conexión al eliminar el vehículo.');
        }
    };

    // ── Modal editar cliente ──────────────────────────────────────────────────

    window.abrirModalCliente = function(id, telefono, direccion) {
        document.getElementById('cliId').value = id;
        document.getElementById('cliTelefono').value = telefono;
        document.getElementById('cliDireccion').value = direccion;

        btnDeleteCli.classList.remove('d-none');

        // Reset vehicle section
        const addInline = document.getElementById('addVehiculoInline');
        const btnAdd    = document.getElementById('btnMostrarAddVehiculo');
        if (addInline) addInline.classList.add('d-none');
        if (btnAdd)    btnAdd.classList.add('d-none');
        ['newVehMatricula', 'newVehModelo', 'newVehMarca', 'newVehAnio'].forEach(fid => {
            const el = document.getElementById(fid);
            if (el) el.value = '';
        });

        cargarVehiculosEnModal(id);
        modalInstance.show();
    };

    // ── Formulario actualizar datos de contacto ───────────────────────────────

    if (formCliente) {
        formCliente.addEventListener('submit', async (e) => {
            e.preventDefault();

            const id = document.getElementById('cliId').value;
            const payload = {
                id_cliente: id,
                telefono:   document.getElementById('cliTelefono').value,
                direccion:  document.getElementById('cliDireccion').value
            };

            try {
                const res = await fetch('/api/clientes', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify(payload)
                });

                if (res.ok) {
                    modalInstance.hide();
                    await window.cargarClientes();
                } else {
                    const errorData = await res.json();
                    alert('Error: ' + (errorData.message || 'No se pudo actualizar el cliente'));
                }
            } catch (err) {
                console.error(err);
                alert('Fallo de red al intentar actualizar el cliente.');
            }
        });
    }

    // ── Botón eliminar cliente ────────────────────────────────────────────────

    if (btnDeleteCli) {
        btnDeleteCli.addEventListener('click', async () => {
            const id = document.getElementById('cliId').value;
            if (!id || !confirm('¿Estás seguro de que deseas ELIMINAR este cliente y su usuario? Esta acción es irreversible.')) return;

            try {
                const res = await fetch('/api/clientes', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({ id_cliente: id })
                });

                if (res.ok) {
                    modalInstance.hide();
                    await window.cargarClientes();
                } else {
                    alert('Error al eliminar el cliente.');
                }
            } catch (err) {
                console.error(err);
                alert('Fallo de red al intentar eliminar el cliente.');
            }
        });
    }

    // ── Formulario añadir cliente nuevo ──────────────────────────────────────

    const formAddCliente = document.getElementById('formAddCliente');
    if (formAddCliente) {
        formAddCliente.addEventListener('submit', async (e) => {
            e.preventDefault();

            const payload = {
                nombre_completo: document.getElementById('addCliNombre').value,
                correo:          document.getElementById('addCliCorreo').value,
                telefono:        document.getElementById('addCliTelefono').value,
                direccion:       document.getElementById('addCliDireccion').value,
                matricula:       document.getElementById('addCliMatricula').value,
                modelo:          document.getElementById('addCliModelo').value,
                marca:           document.getElementById('addCliMarca').value,
                anio:            document.getElementById('addCliAnio').value
            };

            try {
                const res = await fetch('/api/clientes', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify(payload)
                });

                if (res.ok) {
                    const addModal = bootstrap.Modal.getInstance(document.getElementById('addClienteModal'));
                    if (addModal) addModal.hide();
                    formAddCliente.reset();
                    await window.cargarClientes();
                    alert('Cliente creado correctamente.');
                } else {
                    const errorData = await res.json();
                    alert('Error: ' + (errorData.message || 'No se pudo crear el cliente'));
                }
            } catch (err) {
                console.error(err);
                alert('Fallo de red al intentar crear el cliente.');
            }
        });
    }

    // ── Botones de añadir vehículo inline ────────────────────────────────────

    const btnMostrarAddVehiculo = document.getElementById('btnMostrarAddVehiculo');
    const addVehiculoInline     = document.getElementById('addVehiculoInline');
    const btnGuardarNuevoVeh    = document.getElementById('btnGuardarNuevoVeh');
    const btnCancelarNuevoVeh   = document.getElementById('btnCancelarNuevoVeh');

    function resetAddVehiculoForm() {
        ['newVehMatricula', 'newVehModelo', 'newVehMarca', 'newVehAnio'].forEach(fid => {
            const el = document.getElementById(fid);
            if (el) el.value = '';
        });
        if (addVehiculoInline)     addVehiculoInline.classList.add('d-none');
        if (btnMostrarAddVehiculo) btnMostrarAddVehiculo.classList.remove('d-none');
    }

    if (btnMostrarAddVehiculo) {
        btnMostrarAddVehiculo.addEventListener('click', () => {
            addVehiculoInline.classList.remove('d-none');
            btnMostrarAddVehiculo.classList.add('d-none');
        });
    }

    if (btnCancelarNuevoVeh) {
        btnCancelarNuevoVeh.addEventListener('click', resetAddVehiculoForm);
    }

    if (btnGuardarNuevoVeh) {
        btnGuardarNuevoVeh.addEventListener('click', async () => {
            const id_cliente = document.getElementById('cliId').value;
            const matricula  = document.getElementById('newVehMatricula')?.value.trim();
            const modelo     = document.getElementById('newVehModelo')?.value.trim();
            const marca      = document.getElementById('newVehMarca')?.value.trim();
            const anio       = document.getElementById('newVehAnio')?.value;

            if (!matricula || !modelo) { alert('Matrícula y modelo son obligatorios.'); return; }
            if (!id_cliente) return;

            try {
                const res = await fetch('/api/vehiculos', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({ id_cliente, matricula, modelo, marca: marca || null, anio: anio || null })
                });

                if (res.ok) {
                    resetAddVehiculoForm();
                    await cargarVehiculosEnModal(id_cliente);
                    await window.cargarClientes();
                } else {
                    const data = await res.json();
                    alert('Error: ' + (data.message || 'No se pudo añadir el vehículo.'));
                }
            } catch (e) {
                alert('Error de conexión al añadir el vehículo.');
            }
        });
    }
});
