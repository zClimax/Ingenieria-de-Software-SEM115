<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php'; Session::start();
require_once __DIR__ . '/../utils/roles.php'; requireRole(['DOCENTE']);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Tickets | SIGED</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root{ --azul:#0b1a52; --grisBorde:#e5e7eb; --grisLinea:#eef2f7; }
    *{ box-sizing:border-box }
    body{ margin:0; font-family:system-ui,Segoe UI,Roboto,Arial; background:#f8fafc; color:#111827 }
    .wrap{ max-width:1100px; margin:24px auto; padding:0 16px }
    .card{ background:#fff; border:1px solid var(--grisBorde); border-radius:14px;
           box-shadow:0 8px 24px rgba(0,0,0,.06); padding:16px }
    h1{ margin:6px 0 12px; font-size:28px }
    .tabs{ display:flex; gap:8px; margin:8px 0 16px; align-items:center }
    .tab{ padding:8px 12px; border:1px solid var(--grisBorde); border-radius:999px; background:#fff; cursor:pointer }
    .tab.active{ background:var(--azul); color:#fff; border-color:var(--azul) }
    .volver{ margin-left:8px; }
    table{ width:100%; border-collapse:collapse }
    th,td{ padding:10px; border-bottom:1px solid var(--grisLinea); text-align:left; vertical-align:top }
    th{ font-size:12px; color:#6b7280; letter-spacing:.3px; text-transform:uppercase }
    td:nth-child(3){ min-width:220px } /* Descripción */
    td:nth-child(4){ min-width:160px } /* Responsable */
    td:nth-child(5){ min-width:200px } /* Depto */
    .badge{ padding:2px 8px; border-radius:999px; border:1px solid var(--grisBorde); background:#f3f4f6; font-size:12px }
    .btn{ background:var(--azul); color:#fff; border:none; border-radius:10px; padding:10px 14px; cursor:pointer; text-decoration:none; display:inline-block }
    .actions{ display:flex; justify-content:flex-end; margin-top:12px }
    a.link{ color:var(--azul); text-decoration:underline }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h1>Tickets</h1>

      <div class="tabs">
        <button class="tab active" data-t="abiertos">Abiertos</button>
        <button class="tab" data-t="cerrados">Cerrados</button>
        <a class="volver link" href="/SIGED/public/index.php?action=home_docente">← Volver</a>
      </div>

      <table>
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Clave</th>
            <th>Descripción</th>
            <th>Responsable</th>
            <th>Depto</th>
            <th>Estatus</th>
            <th></th>
          </tr>
        </thead>
        <tbody id="tb"></tbody>
      </table>

      <div class="actions">
        <a class="btn" href="/SIGED/public/index.php?action=tk_crear">Crear Ticket</a>
      </div>
    </div>
  </div>

  <script>
  (function(){
    const tb   = document.getElementById('tb');
    const tabs = document.querySelectorAll('.tab');
    let modo   = 'abiertos';

    function render(items){
      tb.innerHTML = '';
      (items || []).forEach(row=>{
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${(row.FECHA_CREACION||'').slice(0,10)}</td>
          <td>${row.ID_TICKET}</td>
          <td>${row.TITULO || row.DESCRIPCION || ''}</td>
          <td>${row.JEFE_NOMBRE || 'Sin asignar'}</td>
          <td>${row.JEFE_DEPTO || '—'}</td>
          <td><span class="badge">${row.ESTATUS}</span></td>
          <td><a class="link" href="/SIGED/public/index.php?action=tk_ver&id=${row.ID_TICKET}">ver</a></td>
        `;
        tb.appendChild(tr);
      });
    }

    function load(){
      fetch('/SIGED/public/index.php?action=tk_data&modo=' + modo, {credentials:'same-origin'})
        .then(r => r.json())
        .then(j => {
          if(!j.ok){ console.error(j); tb.innerHTML = '<tr><td colspan="7">Error al cargar</td></tr>'; return; }
          render(j.items);
        })
        .catch(_ => tb.innerHTML = '<tr><td colspan="7">Sin conexión</td></tr>');
    }

    tabs.forEach(btn => btn.addEventListener('click', ()=>{
      tabs.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      modo = btn.dataset.t;
      load();
    }));

    load();
  })();
  </script>
</body>
</html>
