<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php'; Session::start();
require_once __DIR__ . '/../utils/roles.php'; requireRole(['JEFE_DEPARTAMENTO']);
?>
<!doctype html><html lang="es"><head><meta charset="utf-8">
<title>Tickets (Jefe) | SIGED</title><meta name="viewport" content="width=device-width,initial-scale=1">
<style>
body{margin:0;font-family:system-ui,Segoe UI,Roboto,Arial;background:#f8fafc}
.wrap{max-width:1100px;margin:24px auto;padding:0 16px}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;box-shadow:0 8px 24px rgba(0,0,0,.06);padding:16px}
h1{margin:6px 0 12px}
.tabs{display:flex;gap:8px;margin:8px 0 12px}
.tab{padding:8px 12px;border:1px solid #e5e7eb;border-radius:999px;background:#fff;cursor:pointer}
.tab.active{background:#0b1a52;color:#fff;border-color:#0b1a52}
table{width:100%;border-collapse:collapse}
th,td{padding:10px;border-bottom:1px solid #eef2f7;text-align:left}
th{font-size:12px;color:#6b7280;letter-spacing:.3px;text-transform:uppercase}
.badge{padding:2px 8px;border-radius:999px;border:1px solid #e5e7eb;background:#f3f4f6;font-size:12px}
.prio{font-weight:700}
.link{color:#0b1a52}
</style></head><body>
<div class="wrap">
  <div class="card">
    <h1>Tickets asignados a mí</h1>
    <div class="tabs">
      <button class="tab active" data-t="abiertos">Abiertos</button>
      <button class="tab" data-t="revision">En revisión</button>
      <button class="tab" data-t="cerrados">Cerrados</button>
    </div>
    <table>
      <thead><tr>
        <th>Fecha</th><th>ID</th><th>Título</th><th>Docente</th><th>Prioridad</th><th>Estatus</th><th></th>
      </tr></thead>
      <tbody id="tb"></tbody>
    </table>
  </div>
</div>
<script>
(function(){
  const tb=document.getElementById('tb');
  const tabs=document.querySelectorAll('.tab');
  let modo='abiertos';
  function load(){
    fetch('/SIGED/public/index.php?action=tkj_data&modo='+modo,{credentials:'same-origin'})
      .then(r=>r.json()).then(j=>{
        if(!j.ok){console.error(j);return;}
        tb.innerHTML='';
        (j.items||[]).forEach(row=>{
          const tr=document.createElement('tr');
          tr.innerHTML=`
            <td>${(row.FECHA_CREACION||'').slice(0,10)}</td>
            <td>${row.ID_TICKET}</td>
            <td>${row.TITULO||''}</td>
            <td>${row.DOCENTE||''}</td>
            <td class="prio">${row.PRIORIDAD||''}</td>
            <td><span class="badge">${row.ESTATUS}</span></td>
            <td><a class="link" href="/SIGED/public/index.php?action=tkj_ver&id=${row.ID_TICKET}">atender</a></td>
          `;
          tb.appendChild(tr);
        });
      });
  }
  tabs.forEach(btn=>btn.addEventListener('click',()=>{
    tabs.forEach(b=>b.classList.remove('active')); btn.classList.add('active');
    modo=btn.dataset.t; load();
  }));
  load();
})();
</script>
</body></html>
