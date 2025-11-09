<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../utils/roles.php';
requireRole(['DOCENTE']);
Session::start();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Histórico de convocatorias | SIGED</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root{--azul:#0b1a52;--borde:#e5e7eb}
    body{margin:0;font-family:system-ui,Segoe UI,Roboto,Arial;background:#f8fafc}
    .wrap{max-width:1080px;margin:24px auto;padding:0 16px}
    .card{background:#fff;border:1px solid var(--borde);border-radius:14px;box-shadow:0 8px 24px rgba(0,0,0,.06);padding:16px}
    h1{font-size:22px;margin:4px 0 16px 0;color:#111827}
    table{width:100%;border-collapse:collapse}
    th,td{padding:10px;border-bottom:1px solid #eef2f7;text-align:left}
    th{font-size:12px;letter-spacing:.3px;color:#6b7280;text-transform:uppercase}
    .pill{display:inline-block;padding:4px 8px;border-radius:999px;border:1px solid var(--borde);background:#f3f4f6;font-size:12px}
    .btn{background:#0b1a52;color:#fff;border:none;border-radius:10px;padding:8px 12px;cursor:pointer}
    .back{display:inline-block;margin-bottom:12px;text-decoration:none}
    .muted{color:#6b7280}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
    @media (max-width:720px){.grid{grid-template-columns:1fr}}
  </style>
</head>
<body>
  <div class="wrap">
    <a class="back" href="/SIGED/public/index.php?action=home_docente">← Volver</a>
    <div class="card">
      <h1>Histórico de convocatorias</h1>
      <div id="viewRes">
        <table>
          <thead>
            <tr>
              <th>Año</th>
              <th>Convocatoria</th>
              <th>Vigencia</th>
              <th>Puntos</th>
              <th>%</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="tb"></tbody>
        </table>
      </div>

      <div id="viewDet" style="display:none">
        <div class="grid">
          <div><strong id="detNombre">—</strong></div>
          <div class="muted" id="detVig">—</div>
        </div>
        <table style="margin-top:10px">
          <thead>
            <tr>
              <th>Evidencia</th><th>Puntos</th><th>Estado</th>
            </tr>
          </thead>
          <tbody id="tbDet"></tbody>
        </table>
        <div style="margin-top:12px">
          <button class="btn" id="btnRes">Volver al histórico</button>
        </div>
      </div>
    </div>
  </div>

<script>
(function(){
  const qs = (s)=>document.querySelector(s);
  const tb = qs('#tb');
  const viewRes = qs('#viewRes');
  const viewDet = qs('#viewDet');

  function loadResumen(){
    fetch('/SIGED/public/index.php?action=doc_hist_data',{credentials:'same-origin'})
      .then(r=>r.json()).then(j=>{
        if(!j.ok){ console.error(j); return; }
        viewDet.style.display='none'; viewRes.style.display='block';
        tb.innerHTML='';
        (j.items||[]).forEach(it=>{
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td>${it.ANIO ?? ''}</td>
            <td>${it.NOMBRE_CONVOCATORIA ?? ''}</td>
            <td><span class="muted">${(it.FECHA_INICIO||'').slice(0,10)} → ${(it.FECHA_FIN||'').slice(0,10)}</span></td>
            <td><span class="pill">${it.PUNTOS_OBTENIDOS ?? 0} / ${it.PUNTOS_MAX ?? 300}</span></td>
            <td>${it.PORCENTAJE ?? 0}%</td>
            <td><button class="btn" data-conv="${it.ID_CONVOCATORIA}">Ver detalle</button></td>
          `;
          tb.appendChild(tr);
        });
      });
  }

  function loadDetalle(idConv){
    fetch('/SIGED/public/index.php?action=doc_hist_data&conv='+encodeURIComponent(idConv),{credentials:'same-origin'})
      .then(r=>r.json()).then(j=>{
        if(!j.ok){ console.error(j); return; }
        viewRes.style.display='none'; viewDet.style.display='block';

        const head = j.conv || {};
        qs('#detNombre').textContent = `${head.ANIO || ''} · ${head.NOMBRE_CONVOCATORIA || ''} — ${head.PUNTOS_OBTENIDOS||0}/${head.PUNTOS_MAX||300} (${head.PORCENTAJE||0}%)`;
        qs('#detVig').textContent = `Vigencia: ${(head.FECHA_INICIO||'').slice(0,10)} a ${(head.FECHA_FIN||'').slice(0,10)}`;

        const tbDet = qs('#tbDet'); tbDet.innerHTML='';
        (j.detalle||[]).forEach(d=>{
          const estado = d.tiene ? '✓ Comprobada' : '—';
          const color = d.tiene ? 'style="color:#16a34a;font-weight:600"' : 'class="muted"';
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td>${d.nombre}</td>
            <td>${d.puntos}</td>
            <td ${color}>${estado}</td>
          `;
          tbDet.appendChild(tr);
        });
      });
  }

  document.addEventListener('click', (e)=>{
    const btn = e.target.closest('button[data-conv]');
    if(btn){ loadDetalle(btn.dataset.conv); }
    if(e.target.id==='btnRes'){ loadResumen(); }
  });

  loadResumen();
})();
</script>
</body>
</html>
