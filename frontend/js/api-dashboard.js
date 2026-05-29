const API_URL = '/api/repuestos.php';

document.addEventListener('DOMContentLoaded', () => {

    // ===== 1. GESTIÓN DE VISTAS (SPA - Arquitectura de una sola página) =====
    const menuDashboard    = document.getElementById('menuDashboard');
    const menuInventario   = document.getElementById('menuInventario');
    const menuCalendario   = document.getElementById('menuCalendario');
    const menuReparaciones = document.getElementById('menuReparaciones');
    const menuClientes     = document.getElementById('menuClientes');
    const menuMecanicos    = document.getElementById('menuMecanicos');

    const viewDashboard    = document.getElementById('viewDashboard');
    const viewInventario   = document.getElementById('viewInventario');
    const viewCalendario   = document.getElementById('viewCalendario');
    const viewReparaciones = document.getElementById('viewReparaciones');
    const viewClientes     = document.getElementById('viewClientes');
    const viewMecanicos    = document.getElementById('viewMecanicos');

    const allMenus = [menuDashboard, menuInventario, menuCalendario, menuReparaciones, menuClientes, menuMecanicos];
    const allViews = [viewDashboard, viewInventario, viewCalendario, viewReparaciones, viewClientes, viewMecanicos];

    function hideAllViews() {
        allViews.forEach(v => { if (v) v.classList.add('d-none'); });
        allMenus.forEach(m => { if (m) { m.classList.remove('active'); m.classList.add('sidebar-link'); } });
        viewDashboard.classList.remove('d-flex');
    }

    if (menuDashboard && menuInventario && menuCalendario && menuReparaciones && menuClientes) {
        menuDashboard.addEventListener('click', (e) => {
            e.preventDefault();
            hideAllViews();
            menuDashboard.classList.add('active');
            menuDashboard.classList.remove('sidebar-link');
            viewDashboard.classList.remove('d-none');
            viewDashboard.classList.add('d-flex');
            if (window.cargarColaTrabajos) window.cargarColaTrabajos();
            if (window.cargarCitasHoy) window.cargarCitasHoy();
        });

        menuInventario.addEventListener('click', (e) => {
            e.preventDefault();
            hideAllViews();
            menuInventario.classList.add('active');
            menuInventario.classList.remove('sidebar-link');
            viewInventario.classList.remove('d-none');
            if(window.cargarInventario) window.cargarInventario();
            if(window.cargarSolicitudes) window.cargarSolicitudes();
        });

        menuCalendario.addEventListener('click', (e) => {
            e.preventDefault();
            hideAllViews();
            menuCalendario.classList.add('active');
            menuCalendario.classList.remove('sidebar-link');
            viewCalendario.classList.remove('d-none');
            if (window.calendar) {
                window.calendar.render();
            }
        });

        menuReparaciones.addEventListener('click', (e) => {
            e.preventDefault();
            hideAllViews();
            menuReparaciones.classList.add('active');
            menuReparaciones.classList.remove('sidebar-link');
            viewReparaciones.classList.remove('d-none');
            if(window.cargarReparaciones) window.cargarReparaciones();
        });

        menuClientes.addEventListener('click', (e) => {
            e.preventDefault();
            hideAllViews();
            menuClientes.classList.add('active');
            menuClientes.classList.remove('sidebar-link');
            viewClientes.classList.remove('d-none');
            if (window.cargarClientes) window.cargarClientes();
        });

    }

    // Mecánicos: handler independiente para no bloquear el resto de menús
    if (menuMecanicos && viewMecanicos) {
        menuMecanicos.addEventListener('click', (e) => {
            e.preventDefault();
            hideAllViews();
            menuMecanicos.classList.add('active');
            menuMecanicos.classList.remove('sidebar-link');
            viewMecanicos.classList.remove('d-none');
            if (window.cargarMecanicos) window.cargarMecanicos();
        });
    }


    // ===== 2. LÓGICA CRUD =====
    const formRepuesto = document.getElementById('formRepuesto');
    const formEditRepuesto = document.getElementById('formEditRepuesto');
    const invTableBody = document.getElementById('invTableBody');

    // CREATE (POST)
    if(formRepuesto) {
        formRepuesto.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = formRepuesto.querySelector('button[type="submit"]');
            btn.disabled = true;

            const pNombre = document.getElementById('repNombre').value;
            const pPrecio = parseFloat(document.getElementById('repPrecio').value);
            const pStock = parseInt(document.getElementById('repStock').value);

            try {
                await fetch(API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ nombre_pieza: pNombre, precio_unitario: pPrecio, stock_actual: pStock })
                });

                formRepuesto.reset();
                bootstrap.Modal.getInstance(document.getElementById('addRepuestoModal')).hide();
                await cargarInventario(); // Refresco visual
            } catch (err) {
                console.error(err);
                alert("Hubo un fallo crítico contra Docker. Revisa tu consola.");
            } finally {
                btn.disabled = false;
            }
        });
    }

    // UPDATE (PUT)
    if(formEditRepuesto) {
        formEditRepuesto.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = formEditRepuesto.querySelector('button[type="submit"]');
            btn.disabled = true;

            const pId = parseInt(document.getElementById('editRepId').value);
            const pNombre = document.getElementById('editRepNombre').value;
            const pPrecio = parseFloat(document.getElementById('editRepPrecio').value);
            const pStock = parseInt(document.getElementById('editRepStock').value);

            try {
                await fetch(API_URL, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id_repuesto: pId, nombre_pieza: pNombre, precio_unitario: pPrecio, stock_actual: pStock })
                });

                formEditRepuesto.reset();
                bootstrap.Modal.getInstance(document.getElementById('editRepuestoModal')).hide();
                await cargarInventario();
            } catch (err) {
                console.error(err);
                alert("Error crítico actualizando la base de datos.");
            } finally {
                btn.disabled = false;
            }
        });
    }

    // READ (GET + RENDER)
    window.cargarInventario = async function() {
        if(!invTableBody) return;
        invTableBody.innerHTML = '<tr><td colspan="5" class="text-center p-4"><code>Cargando la base de datos MySQL (Docker)...</code></td></tr>';
        
        try {
            const resp = await fetch(API_URL);
            const repuestos = await resp.json();
            invTableBody.innerHTML = '';

            if (repuestos.length === 0) {
                invTableBody.innerHTML = `<tr><td colspan="5" class="text-center text-muted p-4">La base de datos MySQL de repuestos está vacía. Añade el primero.</td></tr>`;
                return;
            }

            repuestos.forEach(rep => {
                const stockColor = rep.stock_actual < rep.stock_minimo ? 'bg-danger text-white' : 'bg-success text-white';
                
                invTableBody.innerHTML += `
                    <tr>
                        <td class="ps-4 text-muted fw-bold text-start">#${rep.id_repuesto}</td>
                        <td class="fw-bold text-dark text-start">${rep.nombre_pieza}</td>
                        <td><span class="badge ${stockColor} p-2 fs-6 rounded-pill" style="min-width: 60px">${rep.stock_actual} Uds</span></td>
                        <td class="text-primary fw-bold">€${rep.precio_unitario.toFixed(2)}</td>
                        <td class="text-end pe-4">
                            <!-- Botón Editar -->
                            <button class="btn btn-sm btn-outline-primary rounded-circle shadow-sm me-2" 
                                    style="width: 35px; height: 35px;" 
                                    onclick="abrirModalEdicion(${rep.id_repuesto}, '${rep.nombre_pieza.replace(/'/g, "\\'")}', ${rep.precio_unitario}, ${rep.stock_actual})" 
                                    title="Editar Pieza">
                                <i class="fas fa-pencil-alt"></i>
                            </button>
                            <!-- Botón Eliminar -->
                            <button class="btn btn-sm btn-outline-danger rounded-circle shadow-sm" 
                                    style="width: 35px; height: 35px;" 
                                    onclick="borrarRepuesto(${rep.id_repuesto})" 
                                    title="Eliminar Base de Datos">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
        } catch(e) {
            console.error("Error cargando inventario desde Docker: ", e);
            invTableBody.innerHTML = '<tr><td colspan="5" class="text-center text-danger p-4">Error cargando inventario. Revisa tu consola o contenedor.</td></tr>';
        }
    };

    // Funciones Helper Globales para uso In-Line HTML
    window.abrirModalEdicion = function(id, nombre, precio, stock) {
        document.getElementById('editRepId').value = id;
        document.getElementById('editRepNombre').value = nombre;
        document.getElementById('editRepPrecio').value = precio;
        document.getElementById('editRepStock').value = stock;
        
        let modal = new bootstrap.Modal(document.getElementById('editRepuestoModal'));
        modal.show();
    };

    // DELETE (Invoca fetch DELETE)
    window.borrarRepuesto = async function(id) {
        if(confirm("¿Peligro: Seguro que deseas eliminar ESTE repuesto de tu MySQL? ¡Es irreversible!")) {
            try {
                await fetch(API_URL, {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id_repuesto: id })
                });
                await cargarInventario();
            } catch(e) {
                console.error(e);
                alert("Error al borrar el artículo en la nube local.");
            }
        }
    };

    // ===== 3. COLA DE TRABAJOS (DASHBOARD) =====
    window.cargarColaTrabajos = async function() {
        const tbody = document.querySelector('#viewDashboard table tbody');
        if (!tbody) return;
        
        tbody.innerHTML = '<tr><td colspan="3" class="text-center p-4">Cargando cola de trabajos...</td></tr>';
        
        try {
            const res = await fetch('/api/reparaciones', { credentials: 'include' });
            if (res.ok) {
                const reparaciones = await res.json();
                tbody.innerHTML = '';
                
                // Filtrar solo las no finalizadas
                const activas = reparaciones.filter(r => r.estado !== 'Finalizada');
                
                if (activas.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted p-4">No hay trabajos activos en el taller.</td></tr>';
                    return;
                }

                activas.forEach(rep => {
                    let badgeEstado = 'bg-info text-dark';
                    if (rep.estado === 'Esperando Piezas') badgeEstado = 'bg-warning text-dark';

                    const clienteNombre = rep.nombre_cliente || 'Desconocido';
                    const vehiculoStr = `${rep.matricula} - ${rep.modelo_auto}`;

                    tbody.innerHTML += `
                        <tr style="cursor:pointer"
                            onclick="irAReparacion(${rep.id_reparacion}, ${JSON.stringify(rep.modelo_auto)}, ${JSON.stringify(rep.matricula)}, ${JSON.stringify(rep.descripcion_motivo)}, ${JSON.stringify(rep.estado_presupuesto||'')}, ${JSON.stringify(rep.estado)}, ${rep.id_cliente||null})">
                            <td class="ps-4">
                                <div class="fw-bold">${clienteNombre}</div>
                                <div class="small text-muted">${vehiculoStr}</div>
                            </td>
                            <td class="text-truncate" style="max-width: 200px;" title="${rep.descripcion_motivo}">${rep.descripcion_motivo}</td>
                            <td><span class="badge ${badgeEstado} p-2">${rep.estado}</span></td>
                        </tr>
                    `;
                });
            }
        } catch (e) {
            console.error("Error cargando cola de trabajos", e);
            tbody.innerHTML = '<tr><td colspan="3" class="text-center text-danger p-4">Error al cargar la cola de trabajos.</td></tr>';
        }
    };

    window.irAReparacion = function(id, modelo, matricula, descripcion, estadoPres, estadoRep, id_cliente) {
        document.getElementById('menuReparaciones')?.click();
        window.abrirModalReparacion(id, modelo, matricula, descripcion, estadoPres, estadoRep, id_cliente);
    };

    // ===== 4b. CITAS DE HOY =====
    window.cargarCitasHoy = async function() {
        const tbody  = document.getElementById('citasHoyTableBody');
        const kpi    = document.getElementById('kpiCitasHoy');
        const badge  = document.getElementById('kpiCitasHoyBadge');
        if (!tbody) return;

        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Cargando...</td></tr>';

        try {
            const res   = await fetch('/api/citas', { credentials: 'include' });
            const citas = await res.json();

            const hoy     = new Date();
            const yyyyMMdd = `${hoy.getFullYear()}-${String(hoy.getMonth()+1).padStart(2,'0')}-${String(hoy.getDate()).padStart(2,'0')}`;

            const deHoy = citas.filter(c => c.fecha_hora && c.fecha_hora.startsWith(yyyyMMdd));

            if (kpi)   kpi.textContent  = deHoy.length;
            if (badge) badge.textContent = deHoy.length > 0 ? `${deHoy.length} citas` : '';

            if (deHoy.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No hay citas programadas para hoy.</td></tr>';
                return;
            }

            const estadoClase = { Pendiente: 'bg-warning text-dark', Confirmada: 'bg-success', Cancelada: 'bg-secondary' };

            tbody.innerHTML = deHoy.map(c => {
                const hora    = c.fecha_hora.substring(11, 16);
                const cliente = c.nombre_cliente || '—';
                const vehiculo = (c.matricula && c.modelo) ? `${c.matricula} · ${c.modelo}` : (c.matricula || '—');
                const motivo  = (c.motivo || '').length > 50 ? c.motivo.substring(0,50) + '…' : (c.motivo || '—');
                const cls     = estadoClase[c.estado] || 'bg-secondary';
                return `<tr>
                    <td class="ps-4 fw-bold">${hora}</td>
                    <td class="fw-bold">${escDash(cliente)}</td>
                    <td class="text-muted small">${escDash(vehiculo)}</td>
                    <td class="text-truncate" style="max-width:220px" title="${escDash(c.motivo||'')}">${escDash(motivo)}</td>
                    <td><span class="badge ${cls} p-2">${escDash(c.estado)}</span></td>
                </tr>`;
            }).join('');
        } catch(e) {
            console.error('Error cargando citas de hoy', e);
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">Error al cargar las citas.</td></tr>';
        }
    };

    function escDash(str) {
        return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    // Llamar al inicio
    if (viewDashboard && !viewDashboard.classList.contains('d-none')) {
        cargarColaTrabajos();
        cargarCitasHoy();
    }

    // ===== 4. SOLICITUDES DE PIEZAS =====
    const SOLICITUDES_URL = '/api/solicitudes.php';

    window.cargarSolicitudes = async function () {
        const tbody = document.getElementById('solicitudesTableBody');
        const badge = document.getElementById('solicitudesBadge');
        if (!tbody) return;
        try {
            const res  = await fetch(SOLICITUDES_URL, { credentials: 'include' });
            const list = await res.json();
            const pendientes = list.filter(s => s.estado !== 'Recibido').length;
            if (badge) badge.textContent = pendientes > 0 ? pendientes + ' pendientes' : '';

            if (!list.length) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">No hay solicitudes registradas.</td></tr>';
                return;
            }

            const estadoMap = { Pendiente: 'bg-warning text-dark', Pedido: 'bg-info text-dark', Recibido: 'bg-success' };
            tbody.innerHTML = list.map(s => `
                <tr class="${s.estado === 'Recibido' ? 'opacity-50' : ''}">
                    <td class="ps-4 text-start fw-bold">${escSol(s.nombre_pieza)}</td>
                    <td>${parseFloat(s.cantidad)} uds</td>
                    <td class="text-muted small">${s.fecha_solicitud || '—'}</td>
                    <td><span class="badge ${estadoMap[s.estado] || 'bg-secondary'}">${s.estado}</span></td>
                    <td class="text-end pe-4">
                        ${s.estado === 'Pendiente' ? `
                        <button class="btn btn-sm btn-outline-info rounded-pill px-2 me-1"
                                onclick="window.actualizarSolicitud(${s.id_solicitud},'Pedido',${s.id_repuesto||'null'},${s.cantidad})"
                                title="Marcar como pedido">
                            <i class="fas fa-shopping-cart me-1"></i>Pedido
                        </button>` : ''}
                        ${s.estado === 'Pedido' ? `
                        <button class="btn btn-sm btn-outline-success rounded-pill px-2"
                                onclick="window.actualizarSolicitud(${s.id_solicitud},'Recibido',${s.id_repuesto||'null'},${s.cantidad})"
                                title="Marcar como recibido y actualizar stock">
                            <i class="fas fa-check me-1"></i>Recibido
                        </button>` : ''}
                    </td>
                </tr>`).join('');
        } catch (e) {
            if (tbody) tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-3">Error cargando solicitudes.</td></tr>';
        }
    };

    window.actualizarSolicitud = async function (id, estado, id_repuesto, cantidad) {
        try {
            await fetch(SOLICITUDES_URL, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ id_solicitud: id, estado, id_repuesto, cantidad_recibida: cantidad })
            });
            await window.cargarSolicitudes();
            if (estado === 'Recibido' && window.cargarInventario) window.cargarInventario();
        } catch (e) {
            alert('Error actualizando solicitud.');
        }
    };

    function escSol(str) {
        return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }
});
