(async function() {
'use strict';

  // =========================================================================
  // FECHA ACTUAL
  // =========================================================================
const dateEl = document.getElementById('currentDate');
if (dateEl) {
    const hoy = new Date();
    const opciones = { 
      weekday: 'long', 
      year: 'numeric', 
      month: 'long', 
      day: 'numeric' 
    };
    dateEl.textContent = hoy.toLocaleDateString('es-MX', opciones);
  }

  // =========================================================================
  // CAMBIO DE FOTO DE PERFIL (SOLO FOTO GRANDE)
  // =========================================================================
  const avatarContainer = document.getElementById('avatarContainer');
  const fotoInput = document.getElementById('fotoInput');
  const fotoForm = document.getElementById('fotoForm');
  const avatarImg = document.getElementById('avatarImg');
  const avatarImgSmall = document.getElementById('avatarImgSmall');

  if (avatarContainer && fotoInput && fotoForm) {
    // Abrir selector de archivo al hacer clic en el avatar grande
    avatarContainer.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      console.log('Click en avatar detectado');
      fotoInput.click();
    });

    // Procesar archivo seleccionado
    fotoInput.addEventListener('change', function(e) {
      console.log('Archivo seleccionado:', this.files);
      
      if (this.files && this.files[0]) {
        const file = this.files[0];
        
        // Validar tipo de archivo
        if (!['image/jpeg', 'image/jpg'].includes(file.type)) {
          alert('Por favor selecciona una imagen JPG');
          this.value = '';
          return;
        }
        
        // Validar tamaño (2 MB máximo)
        if (file.size > 2 * 1024 * 1024) {
          alert('La imagen es demasiado grande. Máximo 2 MB');
          this.value = '';
          return;
        }
        
        // Preview en ambas fotos (grande y pequeña)
        const reader = new FileReader();
        reader.onload = function(e) {
          avatarImg.src = e.target.result;
          if (avatarImgSmall) {
            avatarImgSmall.src = e.target.result;
          }
        };
        reader.readAsDataURL(file);
        
        // Enviar formulario automáticamente después de 300ms
        console.log('Enviando formulario...');
        setTimeout(() => {
          fotoForm.submit();
        }, 300);
      }
    });
  } else {
    console.error('Elementos no encontrados:', {
      avatarContainer: !!avatarContainer,
      fotoInput: !!fotoInput,
      fotoForm: !!fotoForm
    });
  }

  // =========================================================================
  // FILE INPUT PREVIEW - FIRMA
  // =========================================================================
  const fileInput = document.getElementById('firma');
  const fileName = document.getElementById('fileName');
  
  if (fileInput && fileName) {
    fileInput.addEventListener('change', function() {
      if (this.files && this.files[0]) {
        fileName.textContent = this.files[0].name;
        fileName.style.color = '#191b48';
        fileName.style.fontWeight = '600';
      } else {
        fileName.textContent = 'Ningún archivo seleccionado';
        fileName.style.color = '';
        fileName.style.fontWeight = '';
      }
    });
  }

  // =========================================================================
  // CARGAR DATOS DEL DASHBOARD
  // =========================================================================
  const kEl = document.getElementById('kpis');
  
  function mostrarAviso(msg) {
    kEl.innerHTML = `
      <div class="kpi-card">
        <div class="kpi-label" style="color:#ef4444;">${msg}</div>
      </div>
    `;
  }
  
  // Fetch de datos del servidor
  let data;
  try {
    const res = await fetch('?action=jefe_home_data', { credentials: 'same-origin' });
    const text = await res.text();
    
    if (!res.ok) {
      mostrarAviso(`HTTP ${res.status}: ${text.slice(0, 120)}`);
      return;
    }
    
    data = JSON.parse(text);
    
    if (data.error) {
      mostrarAviso(data.error);
      return;
    }
  } catch (e) {
    mostrarAviso('No se pudo cargar el dashboard');
    console.error(e);
    return;
  }

  const k = data.kpis || {};
  
  // =========================================================================
  // RENDERIZAR KPIs
  // =========================================================================
  kEl.innerHTML = '';
  
  const kpis = [
    { icon: 'bx-check-circle',    label: 'Aprobadas',       value: k.aprobadas ?? '0',            color: '#10b981' },
    { icon: 'bx-time-five',       label: 'Pendientes',      value: k.pendientes ?? '0',           color: '#f59e0b' },
    { icon: 'bx-x-circle',        label: 'Rechazadas',      value: k.rechazadas ?? '0',           color: '#ef4444' },
    { icon: 'bx-error-circle',    label: 'Pend. vencidas',  value: k.pendientes_vencidas ?? '0',  color: '#dc2626' },
    { icon: 'bx-support',         label: 'Tk abiertos',     value: k.tickets_abiertos ?? '0',     color: '#3b82f6' },
    { icon: 'bx-loader-circle',   label: 'Tk en curso',     value: k.tickets_en_curso ?? '0',     color: '#8b5cf6' },
    { icon: 'bx-check-shield',    label: 'Tk cerrados',     value: k.tickets_cerrados ?? '0',     color: '#6b7280' },
    { icon: 'bx-calendar-event',  label: 'Convocatoria',    value: k.convocatoria || '—',         color: '#24268b' }
  ];
  
  kpis.forEach(kpi => {
    const div = document.createElement('div');
    div.className = 'kpi-card';
    div.innerHTML = `
      <div class="kpi-icon" style="color:${kpi.color};">
        <i class='bx ${kpi.icon}'></i>
      </div>
      <div class="kpi-label">${kpi.label}</div>
      <div class="kpi-value">${kpi.value}</div>
    `;
    kEl.appendChild(div);
  });

  // =========================================================================
  // GRÁFICA DE TENDENCIA (30 DÍAS)
  // =========================================================================
  const t = data.tendencia_30d || [];
  const trend = document.getElementById('trend');
  const W = trend.clientWidth || 560;
  const H = 200;
  const pad = 30;
  
  if (t.length > 0) {
    const max = Math.max(1, ...t.map(r => +r.decisiones || 0));
    const xs = t.map((r, i) => pad + (i * (W - 2 * pad) / Math.max(1, t.length - 1)));
    const ys = t.map(r => H - pad - ((+r.decisiones || 0) / max) * (H - 2 * pad));
    
    trend.innerHTML = `
      <svg width="${W}" height="${H}" style="width:100%;height:auto;">
        <defs>
          <linearGradient id="grad1" x1="0%" y1="0%" x2="0%" y2="100%">
            <stop offset="0%" style="stop-color:#5b8def;stop-opacity:0.4" />
            <stop offset="100%" style="stop-color:#5b8def;stop-opacity:0.05" />
          </linearGradient>
        </defs>
        <polyline points="${xs.map((x, i) => `${x},${H - pad}`).join(' ')} ${xs.map((x, i) => `${x},${ys[i]}`).reverse().join(' ')}" 
                  fill="url(#grad1)" stroke="none"/>
        <polyline points="${xs.map((x, i) => `${x},${ys[i]}`).join(' ')}" 
                  fill="none" stroke="#5b8def" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
        ${xs.map((x, i) => `<circle cx="${x}" cy="${ys[i]}" r="4" fill="#fff" stroke="#5b8def" stroke-width="2"/>`).join('')}
        <line x1="${pad}" y1="${H - pad}" x2="${W - pad}" y2="${H - pad}" stroke="#e5e7eb" stroke-width="2"/>
        <line x1="${pad}" y1="${pad}" x2="${pad}" y2="${H - pad}" stroke="#e5e7eb" stroke-width="2"/>
      </svg>
    `;
  } else {
    trend.innerHTML = `
      <div style="color:#9ca3af;text-align:center;padding:60px 20px;">
        <i class="bx bx-line-chart" style="font-size:3rem;opacity:0.5;"></i>
        <p>Sin decisiones en los últimos 30 días</p>
      </div>
    `;
  }

  // =========================================================================
  // RESUMEN DE TICKETS
  // =========================================================================
  const tk = document.getElementById('tickets');
  tk.innerHTML = `
    <li>
      <span>Abiertos</span>
      <strong style="color:#3b82f6;">${k.tickets_abiertos ?? 0}</strong>
    </li>
    <li>
      <span>En curso</span>
      <strong style="color:#8b5cf6;">${k.tickets_en_curso ?? 0}</strong>
    </li>
    <li>
      <span>Cerrados</span>
      <strong style="color:#6b7280;">${k.tickets_cerrados ?? 0}</strong>
    </li>
  `;

  // =========================================================================
  // BACKLOG (SOLICITUDES PENDIENTES)
  // =========================================================================
  const tbody = document.querySelector('#backlog tbody');
  tbody.innerHTML = '';
  
  if ((data.backlog || []).length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="5" style="text-align:center;padding:40px;color:#9ca3af;">
          <i class="bx bx-check-circle" style="font-size:2.5rem;opacity:0.5;margin-bottom:8px;"></i>
          <div>Sin solicitudes pendientes</div>
        </td>
      </tr>
    `;
  } else {
    (data.backlog || []).forEach(r => {
      const tr = document.createElement('tr');
      const fecha = (r.FECHA_ENVIO || r.FECHA_CREACION || '').toString().slice(0, 10);
      const dias = r.dias_pendientes || 0;
      
      tr.innerHTML = `
        <td>
          <a href="/SIGED/public/index.php?action=jefe_ver&id=${r.ID_SOLICITUD}" 
             style="color:#24268b;font-weight:600;text-decoration:none;">
            #${r.ID_SOLICITUD}
          </a>
        </td>
        <td>${r.TIPO_DOCUMENTO || '—'}</td>
        <td>
          <span class="badge-status ${(r.ESTADO || '').toLowerCase()}">
            ${r.ESTADO}
          </span>
        </td>
        <td style="color:${dias > 30 ? '#ef4444' : (dias > 15 ? '#f59e0b' : '#6b7280')};
                   font-weight:${dias > 15 ? '700' : '400'};">
          ${dias} día${dias !== 1 ? 's' : ''}
        </td>
        <td>${fecha}</td>
      `;
      tbody.appendChild(tr);
    });
  }

})();