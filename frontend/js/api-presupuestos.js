/**
 * api-presupuestos.js — Standalone Presupuestos section
 */

document.addEventListener('DOMContentLoaded', () => {

    // ── Cached DOM refs ──
    const presupuestosTableBody = document.getElementById('presupuestosTableBody');
    const filtroClientePres     = document.getElementById('filtroClientePres');
    const formNuevoPresupuesto  = document.getElementById('formNuevoPresupuesto');
    const presCliente           = document.getElementById('presCliente');
    const presVehiculo          = document.getElementById('presVehiculo');
    const presMecanico          = document.getElementById('presMecanico');
    const presLineas            = document.getElementById('presLineas');
    const btnAddLinea           = document.getElementById('btnAddLinea');
    const presTerceros          = document.getElementById('presTerceros');
    const presResumenMaterial   = document.getElementById('presResumenMaterial');
    const presResumenManoObra   = document.getElementById('presResumenManoObra');
    const presGranTotal         = document.getElementById('presGranTotal');

    // In-memory cache of all presupuestos loaded
    let _allPresupuestos = [];

    // ── Load & render presupuestos table ──
    window.cargarPresupuestos = async function (filtroClienteId = null) {
        if (!presupuestosTableBody) return;
        presupuestosTableBody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">Cargando...</td></tr>';

        try {
            const res = await fetch('/api/presupuestos', { credentials: 'include' });
            if (!res.ok) throw new Error('Error HTTP ' + res.status);
            _allPresupuestos = await res.json();
            renderPresupuestosTable(filtroClienteId);
        } catch (e) {
            console.error('Error cargando presupuestos:', e);
            presupuestosTableBody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">Error al cargar presupuestos.</td></tr>';
        }
    };

    function renderPresupuestosTable(filtroClienteId) {
        let lista = _allPresupuestos;

        if (filtroClienteId) {
            const fid = parseInt(filtroClienteId);
            lista = lista.filter(p => parseInt(p.p_id_cliente) === fid);
        }

        if (!lista.length) {
            presupuestosTableBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No hay presupuestos registrados.</td></tr>';
            return;
        }

        presupuestosTableBody.innerHTML = lista.map(p => {
            const estadoBadge = estadoPresupuestoBadge(p.estado);
            const fechaStr = p.fecha_emision ? formatFecha(p.fecha_emision) : '—';
            const totalStr = p.gran_total ? '€ ' + parseFloat(p.gran_total).toFixed(2) : '€ 0.00';
            const clienteStr = escHtml(p.cliente_nombre || '—');
            const vehiculoStr = escHtml(p.vehiculo_str || '—');
            const mecanicoStr = escHtml(p.mecanico_nombre || '—');

            return `
                <tr>
                    <td class="ps-4 text-muted fw-bold text-start">#${p.id_presupuesto}</td>
                    <td class="text-start fw-bold">${clienteStr}</td>
                    <td class="text-start text-muted small">${vehiculoStr}</td>
                    <td class="text-start text-muted small">${mecanicoStr}</td>
                    <td>${fechaStr}</td>
                    <td class="fw-bold text-primary">${totalStr}</td>
                    <td>${estadoBadge}</td>
                    <td class="text-end pe-4">
                        <button class="btn btn-sm btn-outline-primary rounded-circle shadow-sm me-1"
                                style="width:35px;height:35px;"
                                onclick="descargarPresupuesto(${p.id_presupuesto})"
                                title="Descargar PDF">
                            <i class="fas fa-file-pdf"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger rounded-circle shadow-sm"
                                style="width:35px;height:35px;"
                                onclick="eliminarPresupuesto(${p.id_presupuesto})"
                                title="Eliminar">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function estadoPresupuestoBadge(estado) {
        const map = {
            'Borrador':  'bg-secondary',
            'Enviado':   'bg-info text-dark',
            'Aprobado':  'bg-success',
            'Rechazado': 'bg-danger'
        };
        const cls = map[estado] || 'bg-secondary';
        return `<span class="badge ${cls} p-2">${escHtml(estado || '—')}</span>`;
    }

    function formatFecha(dateStr) {
        const d = new Date(dateStr + 'T00:00:00');
        return d.toLocaleDateString('es-ES');
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ── Load filter dropdown ──
    async function cargarFiltroClientes() {
        if (!filtroClientePres) return;
        try {
            const res = await fetch('/api/clientes', { credentials: 'include' });
            if (!res.ok) return;
            const clientes = await res.json();
            filtroClientePres.innerHTML = '<option value="">Todos los clientes</option>';
            clientes.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id_cliente;
                opt.textContent = c.nombre_completo || c.email || 'Cliente #' + c.id_cliente;
                filtroClientePres.appendChild(opt);
            });
        } catch (e) {
            console.error('Error cargando filtro clientes:', e);
        }
    }

    if (filtroClientePres) {
        filtroClientePres.addEventListener('change', () => {
            renderPresupuestosTable(filtroClientePres.value || null);
        });
    }

    // ── Modal: populate Client select ──
    let _clientesLoaded = false;
    async function cargarClientesModal() {
        if (_clientesLoaded || !presCliente) return;
        try {
            const res = await fetch('/api/clientes', { credentials: 'include' });
            if (!res.ok) return;
            const clientes = await res.json();
            presCliente.innerHTML = '<option value="">Seleccione un cliente</option>';
            clientes.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id_cliente;
                opt.textContent = c.nombre_completo || c.email || 'Cliente #' + c.id_cliente;
                presCliente.appendChild(opt);
            });
            _clientesLoaded = true;
        } catch (e) {
            console.error('Error cargando clientes en modal:', e);
        }
    }

    // ── Modal: populate Mechanic select ──
    let _mecanicosLoaded = false;
    async function cargarMecanicosModal() {
        if (_mecanicosLoaded || !presMecanico) return;
        try {
            const res = await fetch('/api/mecanicos', { credentials: 'include' });
            if (!res.ok) return;
            const mecanicos = await res.json();
            presMecanico.innerHTML = '<option value="">Sin mecánico asignado</option>';
            mecanicos.forEach(m => {
                const opt = document.createElement('option');
                opt.value = m.id_mecanico;
                opt.textContent = m.nombre_completo || 'Mecánico #' + m.id_mecanico;
                presMecanico.appendChild(opt);
            });
            _mecanicosLoaded = true;
        } catch (e) {
            console.error('Error cargando mecánicos en modal:', e);
        }
    }

    // ── Modal: load vehicles when client changes ──
    if (presCliente) {
        presCliente.addEventListener('change', async () => {
            const clienteId = presCliente.value;
            if (!presVehiculo) return;
            presVehiculo.innerHTML = '<option value="">Cargando vehículos...</option>';
            presVehiculo.disabled = true;

            if (!clienteId) {
                presVehiculo.innerHTML = '<option value="">Seleccione primero un cliente</option>';
                return;
            }

            try {
                const res = await fetch('/api/vehiculos?id_cliente=' + clienteId, { credentials: 'include' });
                if (!res.ok) throw new Error('Error HTTP ' + res.status);
                const vehiculos = await res.json();
                presVehiculo.innerHTML = '<option value="">Seleccione un vehículo</option>';
                if (!vehiculos.length) {
                    presVehiculo.innerHTML = '<option value="">Sin vehículos registrados</option>';
                } else {
                    vehiculos.forEach(v => {
                        const opt = document.createElement('option');
                        opt.value = v.id_vehiculo;
                        opt.textContent = (v.matricula || '') + ' - ' + (v.modelo || '') + (v.marca ? ' (' + v.marca + ')' : '');
                        presVehiculo.appendChild(opt);
                    });
                    presVehiculo.disabled = false;
                }
            } catch (e) {
                console.error('Error cargando vehículos:', e);
                presVehiculo.innerHTML = '<option value="">Error cargando vehículos</option>';
            }
        });
    }

    // Load modal selects when the modal opens
    const nuevoPresupuestoModal = document.getElementById('nuevoPresupuestoModal');
    if (nuevoPresupuestoModal) {
        nuevoPresupuestoModal.addEventListener('show.bs.modal', () => {
            _clientesLoaded = false;
            _mecanicosLoaded = false;
            cargarClientesModal();
            cargarMecanicosModal();
            resetModal();
        });
    }

    function resetModal() {
        if (formNuevoPresupuesto) formNuevoPresupuesto.reset();
        if (presLineas) presLineas.innerHTML = '';
        if (presVehiculo) { presVehiculo.innerHTML = '<option value="">Seleccione primero un cliente</option>'; presVehiculo.disabled = true; }
        if (presResumenMaterial) presResumenMaterial.textContent = '€ 0.00';
        if (presResumenManoObra) presResumenManoObra.textContent = '€ 0.00';
        if (presGranTotal) presGranTotal.textContent = '€ 0.00';
        if (presTerceros) presTerceros.value = '0';
        addLineaRow(); // Start with one empty row
    }

    // ── Line items: add row ──
    function addLineaRow() {
        if (!presLineas) return;
        const idx = Date.now();
        const row = document.createElement('div');
        row.className = 'row g-1 mb-2 linea-row align-items-center';
        row.dataset.idx = idx;
        row.innerHTML = `
            <div class="col-md-5">
                <input type="text" class="form-control form-control-sm linea-desc" placeholder="Descripción del servicio" oninput="window._presRecalc()">
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm linea-tipo" onchange="window._presRecalc()">
                    <option value="Repuesto">Repuesto</option>
                    <option value="Mano de Obra" selected>Mano de Obra</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="number" class="form-control form-control-sm linea-qty" value="1" min="0.01" step="0.01" oninput="window._presRecalc()">
            </div>
            <div class="col-md-2">
                <input type="number" class="form-control form-control-sm linea-price" value="0" min="0" step="0.01" placeholder="0.00" oninput="window._presRecalc()">
            </div>
            <div class="col-md-1 d-flex align-items-center justify-content-end gap-1">
                <span class="linea-importe small text-primary fw-bold">€0.00</span>
                <button type="button" class="btn btn-sm btn-outline-danger rounded-circle p-0 ms-1" style="width:24px;height:24px;font-size:11px;" onclick="window._presRemoveLinea(this)" title="Eliminar">×</button>
            </div>
        `;
        presLineas.appendChild(row);
        recalcularTotales();
    }

    window._presRemoveLinea = function (btn) {
        const row = btn.closest('.linea-row');
        if (row) row.remove();
        recalcularTotales();
    };

    window._presRecalc = recalcularTotales;

    function recalcularTotales() {
        let material   = 0;
        let manoObra   = 0;

        document.querySelectorAll('#presLineas .linea-row').forEach(row => {
            const qty   = parseFloat(row.querySelector('.linea-qty')?.value  || 0);
            const price = parseFloat(row.querySelector('.linea-price')?.value || 0);
            const tipo  = row.querySelector('.linea-tipo')?.value || 'Mano de Obra';
            const imp   = qty * price;

            const impEl = row.querySelector('.linea-importe');
            if (impEl) impEl.textContent = '€' + imp.toFixed(2);

            if (tipo === 'Repuesto') {
                material += imp;
            } else {
                manoObra += imp;
            }
        });

        const terceros = parseFloat(presTerceros?.value || 0);
        const total    = material + manoObra + terceros;

        if (presResumenMaterial) presResumenMaterial.textContent = '€ ' + material.toFixed(2);
        if (presResumenManoObra) presResumenManoObra.textContent = '€ ' + manoObra.toFixed(2);
        if (presGranTotal)       presGranTotal.textContent       = '€ ' + total.toFixed(2);
    }

    if (btnAddLinea) {
        btnAddLinea.addEventListener('click', addLineaRow);
    }

    if (presTerceros) {
        presTerceros.addEventListener('input', recalcularTotales);
    }

    // ── Form submit ──
    if (formNuevoPresupuesto) {
        formNuevoPresupuesto.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = formNuevoPresupuesto.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Guardando...';

            const clienteId  = parseInt(presCliente?.value  || 0);
            const vehiculoId = parseInt(presVehiculo?.value  || 0);
            const mecanicoId = parseInt(presMecanico?.value  || 0) || null;
            const color      = document.getElementById('presColor')?.value?.trim() || '';
            const km         = parseInt(document.getElementById('presKm')?.value || 0) || null;
            const terceros   = parseFloat(presTerceros?.value || 0);
            const notas      = document.getElementById('presNotas')?.value?.trim() || '';

            if (!clienteId || !vehiculoId) {
                alert('Por favor, selecciona un cliente y un vehículo.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save me-1"></i> Guardar y Descargar PDF';
                return;
            }

            // Collect line items
            const lineas = [];
            document.querySelectorAll('#presLineas .linea-row').forEach(row => {
                const desc  = row.querySelector('.linea-desc')?.value?.trim() || '';
                const tipo  = row.querySelector('.linea-tipo')?.value || 'Mano de Obra';
                const qty   = parseFloat(row.querySelector('.linea-qty')?.value  || 1);
                const price = parseFloat(row.querySelector('.linea-price')?.value || 0);
                if (desc) {
                    lineas.push({ descripcion: desc, tipo, cantidad: qty, precio_unitario: price });
                }
            });

            const payload = {
                standalone: true,
                id_cliente:         clienteId,
                id_vehiculo:        vehiculoId,
                id_mecanico:        mecanicoId,
                color,
                km,
                servicios_terceros: terceros,
                notas,
                lineas
            };

            try {
                const res = await fetch('/api/presupuestos', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify(payload)
                });

                const data = await res.json();

                if (res.ok && data.id_presupuesto) {
                    const modalEl = document.getElementById('nuevoPresupuestoModal');
                    const bsModal = bootstrap.Modal.getInstance(modalEl);
                    if (bsModal) bsModal.hide();
                    await window.cargarPresupuestos();
                } else {
                    alert('Error: ' + (data.message || 'No se pudo crear el presupuesto.'));
                }
            } catch (err) {
                console.error('Error creando presupuesto:', err);
                alert('Error de red al crear el presupuesto.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save me-1"></i> Guardar Presupuesto';
            }
        });
    }

    // ── Global: download existing PDF ──
    window.descargarPresupuesto = function (id) {
        window.open('/api/presupuestos?id_presupuesto=' + id, '_blank');
    };

    // ── Global: delete presupuesto ──
    window.eliminarPresupuesto = async function (id) {
        if (!confirm('¿Seguro que deseas eliminar el Presupuesto #' + id + '? Esta acción es irreversible.')) return;
        try {
            const res = await fetch('/api/presupuestos', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ id_presupuesto: id })
            });
            if (res.ok) {
                await window.cargarPresupuestos(filtroClientePres?.value || null);
            } else {
                const d = await res.json();
                alert('Error: ' + (d.message || 'No se pudo eliminar.'));
            }
        } catch (e) {
            console.error('Error eliminando presupuesto:', e);
            alert('Error de red al eliminar el presupuesto.');
        }
    };

    // ── Init filter on view load ──
    cargarFiltroClientes();
});
