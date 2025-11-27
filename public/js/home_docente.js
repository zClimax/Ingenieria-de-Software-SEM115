document.addEventListener('DOMContentLoaded', () => {
    const base = window.location.pathname.includes('/public/index.php') ? '?action=' : 'index.php?action=';

    // ==========================================
    // 1. FOTO DE PERFIL
    // ==========================================
    const avatarContainer = document.getElementById('avatarContainer');
    const fileInput = document.getElementById('fileInput');

    if (avatarContainer && fileInput) {
        avatarContainer.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', async () => {
            if (!fileInput.files || !fileInput.files[0]) return;
            const formData = new FormData();
            formData.append('foto', fileInput.files[0]);
            try {
                const response = await fetch('/SIGED/public/index.php?action=doc_foto_upload', { method: 'POST', body: formData });
                const data = await response.json();
                if (data.ok) {
                    const nombreArchivo = data.url.split('/').pop(); 
                    const rutaVisual = '../storage/fotos/' + nombreArchivo + '?v=' + new Date().getTime();
                    document.querySelectorAll('.avatar-img-fit').forEach(img => img.src = rutaVisual);
                    document.querySelectorAll('.user-avatar-img-small').forEach(img => img.src = rutaVisual);
                } else { alert('Error: ' + (data.msg || 'No se pudo subir')); }
            } catch (error) { console.error(error); alert('Hubo un error de conexión.'); }
            fileInput.value = '';
        });
    }

    // ==========================================
    // 2. CONVOCATORIA ACTIVA
    // ==========================================
    const modalConv = document.getElementById('convModal');
    const btnConvOk = document.getElementById('btnConvOk');
    let convId = 0;

    async function cargarConvocatoria() {
        try {
            const r = await fetch(base + 'conv_get');
            const d = await r.json();
            if (d.ok && d.mostrar_modal) {
                convId = d.convocatoria?.id || 0;
                document.getElementById('convMeta').textContent = d.convocatoria?.nombre || '';
                const ul = document.getElementById('reqList');
                if(ul) {
                    ul.innerHTML = '';
                    (d.requisitos||[]).forEach(req => {
                        const li = document.createElement('li');
                        li.textContent = `${req.nombre} ${req.cumple?'✓':'✕'}`;
                        li.style.color = req.cumple?'green':'red';
                        ul.appendChild(li);
                    });
                }
                document.getElementById('convMsg').textContent = d.mensaje || '';
                if(modalConv) modalConv.style.display = 'flex';
            }
        } catch (e) { console.error(e); }
    }

    if(btnConvOk) {
        btnConvOk.addEventListener('click', async () => {
            if(convId) {
                const fd = new FormData(); fd.append('id_convocatoria', String(convId));
                await fetch(base + 'conv_ack', { method: 'POST', body: fd });
            }
            if(modalConv) modalConv.style.display = 'none';
        });
    }

    // ==========================================
    // 3. BARRA DE PROGRESO
    // ==========================================
    async function cargarProgreso() {
        const bar = document.getElementById('bar');
        const ptsLabel = document.getElementById('ptsLabel');
        const pctLabel = document.getElementById('pctLabel');
        try {
            const r = await fetch(base + 'doc_home_data');
            const d = await r.json();
            if (d && d.progreso && bar) {
                const pct = Math.max(0, Math.min(100, d.progreso.porcentaje | 0));
                bar.style.width = pct + '%';
                ptsLabel.textContent = (d.progreso.puntos||0) + ' pts.';
                pctLabel.textContent = pct + '%';
            }
        } catch (e) { console.error(e); }
    }

    // ==========================================
    // 4. HISTÓRICO DE CONVOCATORIAS (Lógica Completa)
    // ==========================================
    const btnAbrirHist = document.getElementById('btnAbrirHistorico');
    const modalHist = document.getElementById('modalHistorico');
    const closeHist = document.getElementById('closeHist');
    
    const viewRes = document.getElementById('histViewResumen');
    const viewDet = document.getElementById('histViewDetalle');
    const tbRes = document.getElementById('tbHistResumen');
    const tbDet = document.getElementById('tbHistDetalle');
    const loader = document.getElementById('histLoading');
    const btnVolver = document.getElementById('btnVolverHist');

    // Función para cargar lista principal
    async function loadResumen() {
        viewDet.style.display = 'none';
        viewRes.style.display = 'block';
        tbRes.innerHTML = '';
        loader.style.display = 'block';

        try {
            const r = await fetch(base + 'doc_hist_data');
            const j = await r.json();
            loader.style.display = 'none';

            if (!j.ok) { 
                console.error(j); 
                tbRes.innerHTML = '<tr><td colspan="6" style="text-align:center">Error cargando datos</td></tr>';
                return; 
            }

            if (!j.items || j.items.length === 0) {
                tbRes.innerHTML = '<tr><td colspan="6" style="text-align:center">No hay historial disponible.</td></tr>';
                return;
            }

            j.items.forEach(it => {
                const tr = document.createElement('tr');
                const inicio = (it.FECHA_INICIO || '').substring(0,10);
                const fin = (it.FECHA_FIN || '').substring(0,10);
                
                tr.innerHTML = `
                    <td>${it.ANIO || '-'}</td>
                    <td>${it.NOMBRE_CONVOCATORIA || 'Sin nombre'}</td>
                    <td style="font-size:0.85rem; color:#666;">${inicio} al ${fin}</td>
                    <td><span class="pill">${it.PUNTOS_OBTENIDOS || 0} / ${it.PUNTOS_MAX || 300}</span></td>
                    <td>${it.PORCENTAJE || 0}%</td>
                    <td>
                        <button class="btn-ver-det" data-id="${it.ID_CONVOCATORIA}">
                            Ver detalle
                        </button>
                    </td>
                `;
                tbRes.appendChild(tr);
            });

            // Agregar eventos a botones "Ver detalle"
            document.querySelectorAll('.btn-ver-det').forEach(btn => {
                btn.addEventListener('click', (e) => loadDetalle(e.target.dataset.id));
            });

        } catch (e) {
            console.error(e);
            loader.style.display = 'none';
        }
    }

    // Función para cargar detalle
    async function loadDetalle(idConv) {
        viewRes.style.display = 'none';
        viewDet.style.display = 'block';
        tbDet.innerHTML = '';
        loader.style.display = 'block';

        try {
            const r = await fetch(base + 'doc_hist_data&conv=' + encodeURIComponent(idConv));
            const j = await r.json();
            loader.style.display = 'none';

            if (!j.ok) { console.error(j); return; }

            const h = j.conv || {};
            document.getElementById('detTitulo').textContent = `${h.ANIO || ''} · ${h.NOMBRE_CONVOCATORIA || ''}`;
            document.getElementById('detVigencia').textContent = `Total: ${h.PUNTOS_OBTENIDOS||0} pts (${h.PORCENTAJE||0}%)`;

            (j.detalle || []).forEach(d => {
                const tr = document.createElement('tr');
                const estado = d.tiene ? '<span style="color:green; font-weight:bold;">✓ Entregado</span>' : '<span style="color:#999;">— Pendiente</span>';
                tr.innerHTML = `
                    <td>${d.nombre}</td>
                    <td>${d.puntos} pts</td>
                    <td>${estado}</td>
                `;
                tbDet.appendChild(tr);
            });

        } catch (e) {
            console.error(e);
            loader.style.display = 'none';
        }
    }

    // Eventos del Modal Histórico
    if (btnAbrirHist && modalHist) {
        btnAbrirHist.addEventListener('click', () => {
            modalHist.style.display = 'flex';
            loadResumen(); // Cargar datos al abrir
        });

        closeHist.addEventListener('click', () => modalHist.style.display = 'none');
        
        window.addEventListener('click', (e) => {
            if (e.target === modalHist) modalHist.style.display = 'none';
        });

        if(btnVolver) {
            btnVolver.addEventListener('click', () => {
                viewDet.style.display = 'none';
                viewRes.style.display = 'block';
            });
        }
    }

    // Iniciar resto
    cargarConvocatoria();
    cargarProgreso();
});