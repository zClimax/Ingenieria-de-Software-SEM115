(function() {
    'use strict';

    const APP_DATA = window.APP_DATA || {};
    const tb = document.getElementById('tb');
    const tabs = document.querySelectorAll('.tab');
    let modo = 'abiertos';

    // ========================================
    // RENDERIZAR TABLA
    // ========================================
    function render(items) {
        tb.innerHTML = '';
        
        if (!items || items.length === 0) {
            tb.innerHTML = `
                <tr>
                    <td colspan="7" style="text-align:center; padding:40px; color:#6b7280;">
                        <i class='bx bx-file' style="font-size:3rem; opacity:0.5;"></i>
                        <p style="margin-top:10px;">No hay tickets en esta categoría</p>
                    </td>
                </tr>
            `;
            return;
        }

        items.forEach(row => {
            const tr = document.createElement('tr');
            
            const fecha = (row.FECHA_CREACION || '').slice(0, 10);
            const clave = row.ID_TICKET || '';
            const desc = row.TITULO || row.DESCRIPCION || '';
            const resp = row.JEFE_NOMBRE || 'Sin asignar';
            const depto = row.JEFE_DEPTO || '—';
            const estatus = row.ESTATUS || 'ABIERTO';
            const id = row.ID_TICKET;

            tr.innerHTML = `
                <td>${fecha}</td>
                <td><strong>${clave}</strong></td>
                <td>${desc}</td>
                <td>${resp}</td>
                <td>${depto}</td>
                <td><span class="badge ${estatus}">${estatus}</span></td>
                <td>
                    <a class="link-ver" href="/SIGED/public/index.php?action=tk_ver&id=${id}">
                        ver
                    </a>
                </td>
            `;
            
            tb.appendChild(tr);
        });
    }

    // ========================================
    // CARGAR DATOS
    // ========================================
    function load() {
        tb.innerHTML = `
            <tr>
                <td colspan="7" style="text-align:center; padding:40px; color:#6b7280;">
                    <i class='bx bx-loader-alt bx-spin' style="font-size:2rem;"></i>
                    <p>Cargando tickets...</p>
                </td>
            </tr>
        `;

        fetch('/SIGED/public/index.php?action=tk_data&modo=' + modo, {
            credentials: 'same-origin'
        })
        .then(r => r.json())
        .then(j => {
            if (!j.ok) {
                console.error(j);
                tb.innerHTML = `
                    <tr>
                        <td colspan="7" style="text-align:center; padding:40px; color:#ef4444;">
                            <i class='bx bx-error-circle' style="font-size:3rem;"></i>
                            <p style="margin-top:10px;">Error al cargar tickets</p>
                        </td>
                    </tr>
                `;
                return;
            }
            render(j.items);
        })
        .catch(err => {
            console.error(err);
            tb.innerHTML = `
                <tr>
                    <td colspan="7" style="text-align:center; padding:40px; color:#f59e0b;">
                        <i class='bx bx-wifi-off' style="font-size:3rem;"></i>
                        <p style="margin-top:10px;">Sin conexión</p>
                    </td>
                </tr>
            `;
        });
    }

    // ========================================
    // TABS (CAMBIAR ENTRE ABIERTOS/CERRADOS)
    // ========================================
    tabs.forEach(btn => {
        btn.addEventListener('click', () => {
            tabs.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            modo = btn.dataset.t;
            load();
        });
    });

    // ========================================
    // INICIALIZAR
    // ========================================
    load();

})();