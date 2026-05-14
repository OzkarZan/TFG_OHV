document.addEventListener('DOMContentLoaded', () => {
    const tableBody = document.getElementById('clientesTableBody');
    const formCliente = document.getElementById('formCliente');
    const modalInstance = new bootstrap.Modal(document.getElementById('clienteModal'));
    const btnDeleteCli = document.getElementById('btnDeleteCli');

    window.cargarClientes = async function() {
        if (!tableBody) return;
        tableBody.innerHTML = '<tr><td colspan="5" class="text-center p-4">Cargando clientes...</td></tr>';

        try {
            const res = await fetch('/api/clientes', { credentials: 'include' });
            if (res.ok) {
                const data = await res.json();
                tableBody.innerHTML = '';
                
                if (data.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="5" class="text-center p-4 text-muted">No hay clientes registrados.</td></tr>';
                    return;
                }

                data.forEach(cli => {
                    tableBody.innerHTML += `
                        <tr>
                            <td class="ps-4 fw-bold text-start text-dark">${cli.nombre_completo}</td>
                            <td class="text-start">${cli.correo}</td>
                            <td>${cli.telefono || '<span class="text-muted fst-italic">No especificado</span>'}</td>
                            <td class="text-start text-truncate" style="max-width: 150px;" title="${cli.direccion}">${cli.direccion || '<span class="text-muted fst-italic">No especificada</span>'}</td>
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
            tableBody.innerHTML = '<tr><td colspan="5" class="text-center text-danger p-4">Error de conexión al cargar clientes.</td></tr>';
        }
    };

    window.abrirModalCliente = function(id, telefono, direccion) {
        document.getElementById('cliId').value = id;
        document.getElementById('cliTelefono').value = telefono;
        document.getElementById('cliDireccion').value = direccion;

        if (id) {
            btnDeleteCli.classList.remove('d-none');
        } else {
            btnDeleteCli.classList.add('d-none');
        }

        modalInstance.show();
    };

    if (formCliente) {
        formCliente.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const id = document.getElementById('cliId').value;
            const payload = {
                id_cliente: id,
                telefono: document.getElementById('cliTelefono').value,
                direccion: document.getElementById('cliDireccion').value
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
                    alert("Error: " + (errorData.message || "No se pudo actualizar el cliente"));
                }
            } catch (err) {
                console.error(err);
                alert("Fallo de red al intentar actualizar el cliente.");
            }
        });
    }

    const formAddCliente = document.getElementById('formAddCliente');
    if (formAddCliente) {
        formAddCliente.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const payload = {
                nombre_completo: document.getElementById('addCliNombre').value,
                correo: document.getElementById('addCliCorreo').value,
                telefono: document.getElementById('addCliTelefono').value,
                direccion: document.getElementById('addCliDireccion').value,
                matricula: document.getElementById('addCliMatricula').value,
                modelo: document.getElementById('addCliModelo').value,
                marca: document.getElementById('addCliMarca').value,
                anio: document.getElementById('addCliAnio').value
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
                    alert("Cliente creado correctamente.");
                } else {
                    const errorData = await res.json();
                    alert("Error: " + (errorData.message || "No se pudo crear el cliente"));
                }
            } catch (err) {
                console.error(err);
                alert("Fallo de red al intentar crear el cliente.");
            }
        });
    }

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
                    alert("Error al eliminar el cliente.");
                }
            } catch (err) {
                console.error(err);
                alert("Fallo de red al intentar eliminar el cliente.");
            }
        });
    }
});
