/**
 * Script para la gestión de tickets del jefe
 */

(function() {
    'use strict';

    const tb = document.getElementById('tb');
    const tabs = document.querySelectorAll('.tab');
    let modo = 'abiertos';

    // Función para cargar tickets
    function load() {
        tb.innerHTML = `
            <tr>
                <td colspan="7" class="loading">
                    <i class='bx bx-loader-alt'></i>
                    <p>Cargando tickets...</p>
                </td>
            </tr>
        `;

        fetch('/SIGED/public/index.php?action=tkj_data&modo=' + modo, {
            credentials: 'same-origin'
        })
        .then(r => r.json())
        .then(j => {
            if (!j.ok) {
                console.error('Error al cargar tickets:', j);
                tb.innerHTML = `
                    <tr>
                        <td colspan="7" class="empty-state">
                            <i class='bx bx-error'></i>
                            <h3>Error al cargar</h3>
                            <p>No se pudieron cargar los tickets</p>
                        </td>
                    </tr>
                `;
                return;
            }

            const items = j.items || [];

            if (items.length === 0) {
                tb.innerHTML = `
                    <tr>
                        <td colspan="7" class="empty-state">
                            <i class='bx bx-inbox'></i>
                            <h3>Sin tickets</h3>
                            <p>No hay tickets ${getModoText(modo)} en este momento</p>
                        </td>
                    </tr>
                `;
                return;
            }

            tb.innerHTML = '';
            items.forEach(row => {
                const tr = document.createElement('tr');
                
                // Formatear fecha
                const fecha = (row.FECHA_CREACION || '').slice(0, 10);
                const fechaFormat = fecha ? formatDate(fecha) : '—';

                // Determinar clase de prioridad
                const prioClass = getPriorityClass(row.PRIORIDAD);
                
                // Determinar clase de badge
                const badgeClass = getBadgeClass(row.ESTATUS);

                tr.innerHTML = `
                    <td>${fechaFormat}</td>
                    <td><span class="ticket-id">#${row.ID_TICKET}</span></td>
                    <td><span class="ticket-titulo">${escapeHtml(row.TITULO || 'Sin título')}</span></td>
                    <td>${escapeHtml(row.DOCENTE || '—')}</td>
                    <td><span class="prioridad ${prioClass}">${row.PRIORIDAD || '—'}</span></td>
                    <td><span class="badge ${badgeClass}">${row.ESTATUS}</span></td>
                    <td>
                        <a class="btn-atender" href="/SIGED/public/index.php?action=tkj_ver&id=${row.ID_TICKET}">
                            <i class='bx bx-show'></i>
                            Atender
                        </a>
                    </td>
                `;
                tb.appendChild(tr);
            });

            console.log(`Cargados ${items.length} tickets (${modo})`);
        })
        .catch(err => {
            console.error('Error en fetch:', err);
            tb.innerHTML = `
                <tr>
                    <td colspan="7" class="empty-state">
                        <i class='bx bx-error-circle'></i>
                        <h3>Error de conexión</h3>
                        <p>No se pudo conectar con el servidor</p>
                    </td>
                </tr>
            `;
        });
    }

    // Event listeners para tabs
    tabs.forEach(btn => {
        btn.addEventListener('click', function() {
            tabs.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            modo = this.dataset.t;
            load();
        });
    });

    // Funciones auxiliares
    function formatDate(dateStr) {
        const parts = dateStr.split('-');
        if (parts.length === 3) {
            return `${parts[2]}/${parts[1]}/${parts[0]}`;
        }
        return dateStr;
    }

    function getPriorityClass(prioridad) {
        const p = (prioridad || '').toLowerCase();
        if (p.includes('alta')) return 'alta';
        if (p.includes('media')) return 'media';
        if (p.includes('baja')) return 'baja';
        return '';
    }

    function getBadgeClass(estatus) {
        const e = (estatus || '').toLowerCase();
        if (e.includes('abierto')) return 'abierto';
        if (e.includes('revision') || e.includes('revisión')) return 'revision';
        if (e.includes('cerrado')) return 'cerrado';
        return '';
    }

    function getModoText(m) {
        switch(m) {
            case 'abiertos': return 'abiertos';
            case 'revision': return 'en revisión';
            case 'cerrados': return 'cerrados';
            default: return '';
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Cargar tickets al inicio
    load();

    // Auto-refresh cada 30 segundos
    setInterval(load, 30000);

    console.log('tickets_jefe.js cargado correctamente');
})();