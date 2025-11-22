document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    // ==========================================
    // MENU LATERAL - Funcionalidad del sidebar
    // ==========================================
    const menuToggle = document.getElementById('menu-toggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');

    if (menuToggle && sidebar) {
        // Toggle sidebar open/close
        menuToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('open');
        });

        // Close sidebar when clicking outside on small screens
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 900) {
                if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                    sidebar.classList.remove('open');
                }
            }
        });

        // Keep layout consistent on resize
        window.addEventListener('resize', () => {
            if (window.innerWidth > 900) {
                sidebar.classList.remove('open');
            }
        });

        // Optional: allow Esc to close on small screens
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && window.innerWidth <= 900) {
                sidebar.classList.remove('open');
            }
        });
    }

    // ==========================================
    // TICKETS - Funcionalidad de la tabla
    // ==========================================
    const tb = document.getElementById('tb');
    const tabs = document.querySelectorAll('.tab');
    const btnCrear = document.getElementById('btnCrearTicket');
    
    if (!tb) return; // Si no existe la tabla, no ejecutar el resto

    let modo = 'abiertos';

    // Datos de ejemplo (simulando respuesta del servidor)
    const datosEjemplo = {
        abiertos: [
            {
                FECHA_CREACION: '2025-11-11T00:00:00',
                ID_TICKET: 'D52629',
                TITULO: 'Nombre incorrecto',
                DESCRIPCION: 'Nombre incorrecto',
                ESTATUS: 'Abierto',
                JEFE_NOMBRE: '',
                JEFE_DEPTO: ''
            },
            {
                FECHA_CREACION: '2025-11-25T00:00:00',
                ID_TICKET: 'F52629',
                TITULO: 'Acta sin firma de dept.',
                DESCRIPCION: 'Acta sin firma de dept.',
                ESTATUS: 'En revisión',
                JEFE_NOMBRE: '',
                JEFE_DEPTO: ''
            },
            {
                FECHA_CREACION: '2025-11-18T00:00:00',
                ID_TICKET: 'A52629',
                TITULO: 'Ausencia de acta',
                DESCRIPCION: 'Ausencia de acta',
                ESTATUS: 'Abierto',
                JEFE_NOMBRE: '',
                JEFE_DEPTO: ''
            }
        ],
        cerrados: [
            {
                FECHA_CREACION: '2025-10-15T00:00:00',
                ID_TICKET: 'C42123',
                TITULO: 'Problema resuelto',
                DESCRIPCION: 'Problema resuelto correctamente',
                ESTATUS: 'Cerrado',
                JEFE_NOMBRE: 'Dr. Juan Pérez',
                JEFE_DEPTO: 'Sistemas'
            },
            {
                FECHA_CREACION: '2025-09-22T00:00:00',
                ID_TICKET: 'B38945',
                TITULO: 'Solicitud completada',
                DESCRIPCION: 'Solicitud de cambio completada',
                ESTATUS: 'Cerrado',
                JEFE_NOMBRE: 'Mtro. Carlos López',
                JEFE_DEPTO: 'Administración'
            }
        ]
    };
/**
 * Renderiza las filas de la tabla con los datos proporcionados
 */
function render(items) {
    tb.innerHTML = '';
    
    if (!items || items.length === 0) {
        tb.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:24px; color:#9ca3af;">No hay tickets para mostrar</td></tr>';
        return;
    }

    items.forEach(row => {
        const tr = document.createElement('tr');
        
        // Formatear fecha (MM-DD-YYYY)
        let fechaFormateada = '—';
        if (row.FECHA_CREACION) {
            const fecha = new Date(row.FECHA_CREACION);
            const mes = String(fecha.getMonth() + 1).padStart(2, '0');
            const dia = String(fecha.getDate()).padStart(2, '0');
            const anio = fecha.getFullYear();
            fechaFormateada = `${mes}-${dia}-${anio}`;
        }
        
        // Determinar clase del badge según estatus
        let badgeClass = 'badge';
        const estatus = (row.ESTATUS || '').toLowerCase();
        if (estatus.includes('abierto')) {
            badgeClass += ' abierto';
        } else if (estatus.includes('revisión')) {
            badgeClass += ' revision';
        } else if (estatus.includes('cerrado')) {
            badgeClass += ' cerrado';
        }

        tr.innerHTML = `
            <td>${fechaFormateada}</td>
            <td><strong>${row.ID_TICKET}</strong></td>
            <td>${row.TITULO || row.DESCRIPCION || '—'}</td>
            <td><span class="${badgeClass}">${row.ESTATUS}</span></td>
            <td>
                <button class="btn-ver" data-id="${row.ID_TICKET}">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                </button>
            </td>
        `;
        
        tb.appendChild(tr);
    });

    // Agregar eventos a botones "ver"
    document.querySelectorAll('.btn-ver').forEach(btn => {
        btn.addEventListener('click', function() {
            const ticketId = this.dataset.id;
            verTicket(ticketId);
        });
    });
}


    /**
     * Carga los datos según el modo (abiertos/cerrados)
     * En producción, esto haría un fetch al servidor
     */
    function load() {
        // Simulación de carga
        tb.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:24px;">Cargando...</td></tr>';

        // Simular delay de red
        setTimeout(() => {
            // ===== MODO PRODUCCIÓN =====
            // Descomenta esto cuando conectes con el backend PHP:
            /*
            fetch(`/SIGED/public/index.php?action=tk_data&modo=${modo}`, {
                credentials: 'same-origin',
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Error en la respuesta del servidor');
                }
                return response.json();
            })
            .then(data => {
                if (!data.ok) {
                    console.error('Error en datos:', data);
                    tb.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:24px; color:#ef4444;">Error al cargar datos del servidor</td></tr>';
                    return;
                }
                render(data.items);
            })
            .catch(error => {
                console.error('Error de conexión:', error);
                tb.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:24px; color:#ef4444;">Sin conexión al servidor</td></tr>';
            });
            */

            // ===== MODO DESARROLLO =====
            // Comentar/eliminar esto en producción:
            const items = datosEjemplo[modo] || [];
            render(items);
        }, 400);
    }

    /**
     * Ver detalle del ticket
     */
    function verTicket(ticketId) {
        // En producción, descomentar esta línea:
        // window.location.href = `/SIGED/public/index.php?action=tk_ver&id=${ticketId}`;
        
        // Modo desarrollo:
        console.log(`Ver ticket: ${ticketId}`);
        alert(`Ver detalle del ticket: ${ticketId}`);
    }

    /**
     * Crear nuevo ticket
     */
    function crearTicket() {
        // En producción, descomentar esta línea:
        // window.location.href = '/SIGED/public/index.php?action=tk_crear';
        
        // Modo desarrollo:
        console.log('Crear nuevo ticket');
        alert('Navegar a formulario de crear ticket');
    }

    // Configurar eventos de tabs
    if (tabs.length > 0) {
        tabs.forEach(btn => {
            btn.addEventListener('click', function() {
                // Cerrar sidebar en mobile al cambiar de tab
                if (window.innerWidth <= 900 && sidebar) {
                    sidebar.classList.remove('open');
                }
                
                // Cambiar tab activo
                tabs.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                // Cambiar modo y recargar
                modo = this.dataset.t;
                load();
            });
        });
    }

    // Evento para crear ticket
    if (btnCrear) {
        btnCrear.addEventListener('click', () => {
            // Cerrar sidebar en mobile
            if (window.innerWidth <= 900 && sidebar) {
                sidebar.classList.remove('open');
            }
            crearTicket();
        });
    }

    // Carga inicial de datos
    load();
});
