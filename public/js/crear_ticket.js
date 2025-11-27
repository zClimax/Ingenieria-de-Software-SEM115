(function() {
    'use strict';

    const APP_DATA = window.APP_DATA || {};
    const sel = document.getElementById('responsable');
    const sug = document.getElementById('sugerencia');
    const sol = APP_DATA.solId || 0;

    // ========================================
    // CARGAR JEFES DE DEPARTAMENTO
    // ========================================
    async function loadJefes() {
        try {
            const url = '/SIGED/public/index.php?action=tk_resp_data' + (sol ? ('&sol=' + sol) : '');
            const r = await fetch(url, { credentials: 'same-origin' });
            const j = await r.json();

            if (!j.ok) {
                sel.innerHTML = '<option value="">Error al cargar jefes</option>';
                sug.innerHTML = '<i class="bx bx-error-circle"></i><span style="color:#ef4444;">No fue posible cargar la lista de responsables.</span>';
                return;
            }

            // Agrupar por departamento
            const byDept = {};
            (j.items || []).forEach(it => {
                const depId = it.ID_DEPARTAMENTO || 0;
                const depName = it.NOMBRE_DEPARTAMENTO || 'Sin departamento';
                if (!byDept[depId]) {
                    byDept[depId] = { name: depName, users: [] };
                }
                byDept[depId].users.push(it);
            });

            // Renderizar select
            function render(filterDeptId = null) {
                sel.innerHTML = '';
                const entries = Object.entries(byDept)
                    .filter(([depId, _]) => filterDeptId === null || Number(depId) === Number(filterDeptId));

                if (entries.length === 0) {
                    sel.innerHTML = '<option value="">No hay jefes disponibles</option>';
                    return;
                }

                entries.forEach(([depId, group]) => {
                    const og = document.createElement('optgroup');
                    og.label = group.name;
                    
                    group.users.forEach(u => {
                        const opt = document.createElement('option');
                        opt.value = u.ID_USUARIO;
                        opt.textContent = `${u.NOMBRE_MOSTRAR}`;
                        
                        // Marcar recomendado
                        if (Number(u.ID_DEPARTAMENTO) === Number(j.depto_sugerido)) {
                            opt.dataset.recomendado = '1';
                        }
                        og.appendChild(opt);
                    });
                    
                    sel.appendChild(og);
                });

                // Seleccionar recomendado
                const rec = sel.querySelector('option[data-recomendado="1"]');
                if (rec) {
                    sel.value = rec.value;
                }
            }

            // Si viene desde una solicitud → filtrar
            let filtrado = false;
            if (sol && j.depto_sugerido) {
                render(j.depto_sugerido);
                filtrado = true;
                sug.innerHTML = `
                    <i class='bx bx-check-circle' style="color:#10b981;"></i>
                    <span>Departamento sugerido según la solicitud. <a href="#" id="verTodos">Ver todos los departamentos</a></span>
                `;
            } else {
                render(null);
                sug.innerHTML = `
                    <i class='bx bx-info-circle'></i>
                    <span>Seleccione el jefe responsable del departamento correspondiente.</span>
                `;
            }

            // Toggle "ver todos"
            document.addEventListener('click', (e) => {
                if (e.target && e.target.id === 'verTodos') {
                    e.preventDefault();
                    render(null);
                    sug.innerHTML = `
                        <i class='bx bx-info-circle'></i>
                        <span>Mostrando todos los departamentos disponibles.</span>
                    `;
                }
            });

        } catch (err) {
            console.error('Error cargando jefes:', err);
            sel.innerHTML = '<option value="">Error de conexión</option>';
            sug.innerHTML = '<i class="bx bx-wifi-off"></i><span style="color:#f59e0b;">Sin conexión al servidor.</span>';
        }
    }

    // ========================================
    // VALIDACIÓN DEL FORMULARIO
    // ========================================
    const form = document.querySelector('.ticket-form');
    if (form) {
        form.addEventListener('submit', (e) => {
            const titulo = document.getElementById('titulo').value.trim();
            const responsable = document.getElementById('responsable').value;
            const descripcion = document.getElementById('descripcion').value.trim();

            if (!titulo || !responsable || !descripcion) {
                e.preventDefault();
                alert('Por favor completa todos los campos obligatorios.');
                return false;
            }

            if (titulo.length < 5) {
                e.preventDefault();
                alert('El título debe tener al menos 5 caracteres.');
                return false;
            }

            if (descripcion.length < 10) {
                e.preventDefault();
                alert('La descripción debe tener al menos 10 caracteres.');
                return false;
            }
        });
    }

    // ========================================
    // INICIALIZAR
    // ========================================
    loadJefes();

})();