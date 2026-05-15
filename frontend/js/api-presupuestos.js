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

    let _allPresupuestos = [];
    let _repuestos       = [];  // inventory cache

    // ── Load inventory once for the part selector ──
    async function cargarRepuestosSelector() {
        if (_repuestos.length > 0) return;
        try {
            const res = await fetch('/api/repuestos.php', { credentials: 'include' });
            if (res.ok) _repuestos = await res.json();
        } catch (e) { /* inventory unavailable — selector just stays empty */ }
    }

    // ── Populate a repuesto <select> from the cache ──
    function populateRepuestoSelect(sel) {
        sel.innerHTML = '<option value="">— Seleccionar del inventario —</option>';
        _repuestos.forEach(r => {
            const opt = document.createElement('option');
            opt.value = r.id_repuesto;
            opt.dataset.precio  = r.precio_unitario;
            opt.dataset.stock   = r.stock_actual;
            opt.dataset.nombre  = r.nombre_pieza;
            const stockLabel = r.stock_actual > 0 ? `Stock: ${r.stock_actual}` : 'Sin stock';
            opt.textContent = `${r.nombre_pieza}${r.marca ? ' (' + r.marca + ')' : ''} — ${stockLabel} — €${parseFloat(r.precio_unitario).toFixed(2)}`;
            sel.appendChild(opt);
        });

        sel.addEventListener('change', function () {
            const row  = this.closest('.linea-row');
            const opt  = this.options[this.selectedIndex];
            const badge = row.querySelector('.linea-stock-badge');

            if (!this.value) {
                row.dataset.idRepuesto = '';
                if (badge) { badge.textContent = ''; badge.className = 'linea-stock-badge'; }
                return;
            }

            const precio = parseFloat(opt.dataset.precio) || 0;
            const stock  = parseInt(opt.dataset.stock)    || 0;
            const nombre = opt.dataset.nombre             || '';
            const qty    = parseFloat(row.querySelector('.linea-qty')?.value || 1);

            row.querySelector('.linea-desc').value  = nombre;
            row.querySelector('.linea-price').value = precio.toFixed(2);
            row.dataset.idRepuesto = this.value;

            if (badge) {
                if (stock >= qty) {
                    badge.className   = 'linea-stock-badge badge bg-success ms-1';
                    badge.textContent = `En stock (${stock} uds)`;
                } else if (stock > 0) {
                    badge.className   = 'linea-stock-badge badge bg-warning text-dark ms-1';
                    badge.textContent = `Stock insuficiente (${stock}) — se pedirá`;
                } else {
                    badge.className   = 'linea-stock-badge badge bg-danger ms-1';
                    badge.textContent = 'Sin stock — se creará solicitud';
                }
            }
            recalcularTotales();
        });
    }

    // ── Load & render presupuestos table ──
    window.cargarPresupuestos = async function (filtroClienteId = null) {
        if (!presupuestosTableBody) return;
        presupuestosTableBody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">Cargando...</td></tr>';
        try {
            const res = await fetch('/api/presupuestos', { credentials: 'include' });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            _allPresupuestos = await res.json();
            renderPresupuestosTable(filtroClienteId);
        } catch (e) {
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
            const badge      = estadoPresupuestoBadge(p.estado);
            const fechaStr   = p.fecha_emision ? formatFecha(p.fecha_emision) : '—';
            const totalStr   = '€ ' + parseFloat(p.gran_total || 0).toFixed(2);
            return `
                <tr>
                    <td class="ps-4 text-muted fw-bold text-start">#${p.id_presupuesto}</td>
                    <td class="text-start fw-bold">${escHtml(p.cliente_nombre || '—')}</td>
                    <td class="text-start text-muted small">${escHtml(p.vehiculo_str || '—')}</td>
                    <td class="text-start text-muted small">${escHtml(p.mecanico_nombre || '—')}</td>
                    <td>${fechaStr}</td>
                    <td class="fw-bold text-primary">${totalStr}</td>
                    <td>${badge}</td>
                    <td class="text-end pe-4">
                        <button class="btn btn-sm btn-outline-primary rounded-circle shadow-sm me-1"
                                style="width:35px;height:35px;"
                                onclick="descargarPresupuesto(${p.id_presupuesto})" title="Descargar PDF">
                            <i class="fas fa-file-pdf"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger rounded-circle shadow-sm"
                                style="width:35px;height:35px;"
                                onclick="eliminarPresupuesto(${p.id_presupuesto})" title="Eliminar">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>`;
        }).join('');
    }

    function estadoPresupuestoBadge(estado) {
        const map = { 'Borrador': 'bg-secondary', 'Enviado': 'bg-info text-dark', 'Aprobado': 'bg-success', 'Rechazado': 'bg-danger' };
        return `<span class="badge ${map[estado] || 'bg-secondary'} p-2">${escHtml(estado || '—')}</span>`;
    }

    function formatFecha(d) {
        return new Date(d + 'T00:00:00').toLocaleDateString('es-ES');
    }

    function escHtml(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ── Filter dropdown ──
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
        } catch (e) {}
    }

    if (filtroClientePres) {
        filtroClientePres.addEventListener('change', () => renderPresupuestosTable(filtroClientePres.value || null));
    }

    // ── Modal: populate selects ──
    let _clientesLoaded  = false;
    let _mecanicosLoaded = false;

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
        } catch (e) {}
    }

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
        } catch (e) {}
    }

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
                presVehiculo.innerHTML = '<option value="">Error cargando vehículos</option>';
            }
        });
    }

    const nuevoPresupuestoModal = document.getElementById('nuevoPresupuestoModal');
    if (nuevoPresupuestoModal) {
        nuevoPresupuestoModal.addEventListener('show.bs.modal', () => {
            _clientesLoaded  = false;
            _mecanicosLoaded = false;
            cargarClientesModal();
            cargarMecanicosModal();
            cargarRepuestosSelector();
            resetModal();
        });
    }

    function resetModal() {
        if (formNuevoPresupuesto) formNuevoPresupuesto.reset();
        if (presLineas) presLineas.innerHTML = '';
        if (presVehiculo) { presVehiculo.innerHTML = '<option value="">Seleccione primero un cliente</option>'; presVehiculo.disabled = true; }
        if (presResumenMaterial) presResumenMaterial.textContent = '€ 0.00';
        if (presResumenManoObra) presResumenManoObra.textContent = '€ 0.00';
        if (presGranTotal)       presGranTotal.textContent       = '€ 0.00';
        if (presTerceros)        presTerceros.value = '0';
        addLineaRow();
    }

    // ── Line items ──
    function addLineaRow() {
        if (!presLineas) return;
        const row = document.createElement('div');
        row.className = 'row g-1 mb-2 linea-row align-items-start';
        row.dataset.idRepuesto = '';
        row.innerHTML = `
            <div class="col-md-5">
                <input type="text" class="form-control form-control-sm linea-desc"
                       placeholder="Descripción del servicio" oninput="window._presRecalc()">
                <div class="linea-repuesto-picker d-none mt-1">
                    <select class="form-select form-select-sm linea-repuesto-select"></select>
                    <span class="linea-stock-badge"></span>
                </div>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm linea-tipo" onchange="window._presTipoChange(this)">
                    <option value="Repuesto">Repuesto</option>
                    <option value="Mano de Obra" selected>Mano de Obra</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="number" class="form-control form-control-sm linea-qty"
                       value="1" min="0.01" step="0.01" oninput="window._presRecalc()">
            </div>
            <div class="col-md-2">
                <input type="number" class="form-control form-control-sm linea-price"
                       value="0" min="0" step="0.01" placeholder="0.00" oninput="window._presRecalc()">
            </div>
            <div class="col-md-1 d-flex align-items-center justify-content-end gap-1 pt-1">
                <span class="linea-importe small text-primary fw-bold">€0.00</span>
                <button type="button" class="btn btn-sm btn-outline-danger rounded-circle p-0 ms-1"
                        style="width:24px;height:24px;font-size:11px;"
                        onclick="window._presRemoveLinea(this)" title="Eliminar">×</button>
            </div>`;
        presLineas.appendChild(row);
        recalcularTotales();
    }

    // Called when tipo select changes
    window._presTipoChange = function (select) {
        const row    = select.closest('.linea-row');
        const picker = row.querySelector('.linea-repuesto-picker');
        const badge  = row.querySelector('.linea-stock-badge');

        if (select.value === 'Repuesto') {
            picker.classList.remove('d-none');
            const sel = picker.querySelector('.linea-repuesto-select');
            // Populate once, or re-populate if inventory was loaded after row creation
            if (sel.options.length <= 1) populateRepuestoSelect(sel);
        } else {
            picker.classList.add('d-none');
            row.dataset.idRepuesto = '';
            if (badge) { badge.textContent = ''; badge.className = 'linea-stock-badge'; }
        }
        recalcularTotales();
    };

    window._presRemoveLinea = function (btn) {
        btn.closest('.linea-row')?.remove();
        recalcularTotales();
    };

    window._presRecalc = recalcularTotales;

    function recalcularTotales() {
        let material = 0, manoObra = 0;
        document.querySelectorAll('#presLineas .linea-row').forEach(row => {
            const qty   = parseFloat(row.querySelector('.linea-qty')?.value  || 0);
            const price = parseFloat(row.querySelector('.linea-price')?.value || 0);
            const tipo  = row.querySelector('.linea-tipo')?.value || 'Mano de Obra';
            const imp   = qty * price;
            const impEl = row.querySelector('.linea-importe');
            if (impEl) impEl.textContent = '€' + imp.toFixed(2);
            if (tipo === 'Repuesto') material  += imp;
            else                     manoObra  += imp;
        });
        const terceros = parseFloat(presTerceros?.value || 0);
        if (presResumenMaterial) presResumenMaterial.textContent = '€ ' + material.toFixed(2);
        if (presResumenManoObra) presResumenManoObra.textContent = '€ ' + manoObra.toFixed(2);
        if (presGranTotal)       presGranTotal.textContent       = '€ ' + (material + manoObra + terceros).toFixed(2);
    }

    if (btnAddLinea)  btnAddLinea.addEventListener('click', addLineaRow);
    if (presTerceros) presTerceros.addEventListener('input', recalcularTotales);

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
                btn.innerHTML = '<i class="fas fa-save me-1"></i> Guardar Presupuesto';
                return;
            }

            const lineas = [];
            document.querySelectorAll('#presLineas .linea-row').forEach(row => {
                const desc        = row.querySelector('.linea-desc')?.value?.trim() || '';
                const tipo        = row.querySelector('.linea-tipo')?.value || 'Mano de Obra';
                const qty         = parseFloat(row.querySelector('.linea-qty')?.value  || 1);
                const price       = parseFloat(row.querySelector('.linea-price')?.value || 0);
                const id_repuesto = row.dataset.idRepuesto ? parseInt(row.dataset.idRepuesto) : null;
                if (desc) lineas.push({ descripcion: desc, tipo, cantidad: qty, precio_unitario: price, id_repuesto });
            });

            const payload = {
                standalone: true,
                id_cliente: clienteId, id_vehiculo: vehiculoId, id_mecanico: mecanicoId,
                color, km, servicios_terceros: terceros, notas, lineas
            };

            try {
                const res  = await fetch('/api/presupuestos', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (res.ok && data.id_presupuesto) {
                    bootstrap.Modal.getInstance(document.getElementById('nuevoPresupuestoModal'))?.hide();
                    await window.cargarPresupuestos();
                    // Refresh inventory if visible (stock may have changed)
                    if (window.cargarInventario) window.cargarInventario();
                    if (window.cargarSolicitudes) window.cargarSolicitudes();
                } else {
                    alert('Error: ' + (data.message || 'No se pudo crear el presupuesto.'));
                }
            } catch (err) {
                alert('Error de red al crear el presupuesto.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save me-1"></i> Guardar Presupuesto';
            }
        });
    }

    window.descargarPresupuesto = function (id) {
        window.open('/api/presupuestos?id_presupuesto=' + id, '_blank');
    };

    window.eliminarPresupuesto = async function (id) {
        if (!confirm('¿Seguro que deseas eliminar el Presupuesto #' + id + '? Esta acción es irreversible.')) return;
        try {
            const res = await fetch('/api/presupuestos', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ id_presupuesto: id })
            });
            if (res.ok) await window.cargarPresupuestos(filtroClientePres?.value || null);
            else { const d = await res.json(); alert('Error: ' + (d.message || 'No se pudo eliminar.')); }
        } catch (e) { alert('Error de red al eliminar el presupuesto.'); }
    };

    cargarFiltroClientes();
});
