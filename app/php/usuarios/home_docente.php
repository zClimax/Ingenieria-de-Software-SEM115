<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php';
$user = Session::user();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>SIGED · Docente</title>
  <link rel="stylesheet" href="/siged/app/css/styles.css">
  <script defer src="/siged/app/js/main.js"></script>
</head>

<?php

if (!class_exists('Session')) {
  @require_once __DIR__ . '/../utils/session.php';
}
if (class_exists('Session')) {
  Session::start();
  $___usr = Session::user();
  $___rol = $___usr['rol'] ?? '';
} else {
  $___rol = '';
}

if ($___rol === 'DOCENTE'):
  
?>
<style>
#siged-modal-overlay{position:fixed;inset:0;background:rgba(0,10,30,.55);display:none;align-items:center;justify-content:center;z-index:9999}
#siged-modal{width:min(680px,95vw);background:#fff;border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,.25);overflow:hidden;font-family:system-ui,Segoe UI,Roboto,Arial}
#siged-modal header{background:#0b1a52;color:#fff;padding:16px 20px;display:flex;align-items:center;gap:10px}
#siged-modal .content{padding:22px}
.siged-check{color:#16a34a;font-weight:600;margin:10px 0;display:flex;align-items:center;gap:8px}
.siged-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:10px}
.siged-grid ul{list-style:none;margin:0;padding:0}
.siged-grid li{display:flex;align-items:center;justify-content:space-between;padding:6px 10px;border-radius:10px;background:#f9fafb;border:1px solid #eef2f7;margin-bottom:6px}
.siged-dot{width:10px;height:10px;border-radius:999px;margin-left:10px}
.siged-det{margin-top:6px;font-size:12px;color:#7f1d1d;background:#fff1f2;border:1px solid #fecdd3;border-radius:8px;padding:6px 8px}
#siged-modal footer{padding:12px 20px;border-top:1px solid #e5e7eb;display:flex;justify-content:center}
#siged-close{background:#0b1a52;color:#fff;border:none;border-radius:10px;padding:8px 20px;cursor:pointer}
@media (max-width:560px){.siged-grid{grid-template-columns:1fr}}
</style>

<div id="siged-modal-overlay" aria-hidden="true">
  <div id="siged-modal" role="dialog" aria-modal="true" aria-labelledby="siged-title">
    <header><strong>SIGED</strong></header>
    <div class="content">
      <h3 id="siged-title" style="margin:0 0 6px 0;">Validación de requisito – <span id="siged-conv-nombre">Convocatoria</span></h3>
      <p id="siged-conv-sub" style="margin:0 0 12px 0;color:#374151">Vigencia</p>
      <div class="siged-check"><span style="font-size:22px">✅</span>
        <span id="siged-mensaje">Cumple los requisitos para participar.</span>
      </div>
      <div class="siged-grid">
        <div><h4>Requisitos</h4><ul id="siged-req-nombres"></ul></div>
        <div><h4>Estado</h4><ul id="siged-req-estados"></ul></div>
      </div>
    </div>
    <footer><button id="siged-close">Cerrar</button></footer>
  </div>
</div>

<script>
(function(){
  const overlay = document.getElementById('siged-modal-overlay');
  if(!overlay) return;

  fetch('/SIGED/public/index.php?action=conv_get', {credentials:'same-origin'})
    .then(r=>r.json())
    .then(data=>{
      if(!data || !data.ok || !data.mostrar_modal) return;

      const conv = data.convocatoria || {};
      document.getElementById('siged-conv-nombre').textContent = conv.nombre || '';
      document.getElementById('siged-conv-sub').textContent = 'Vigencia: ' + (conv.fecha_ini||'') + ' a ' + (conv.fecha_fin||'');
      document.getElementById('siged-mensaje').textContent = data.mensaje || 'Validación de requisitos';

      const ulN = document.getElementById('siged-req-nombres');
      const ulE = document.getElementById('siged-req-estados');
      ulN.innerHTML = ''; ulE.innerHTML = '';

      (data.requisitos || []).forEach(req=>{
        const liN = document.createElement('li');
        liN.textContent = req.nombre || '';
        ulN.appendChild(liN);

        const liE = document.createElement('li');
        liE.style.alignItems = 'start';

        const box = document.createElement('div');
        box.style.display = 'flex';
        box.style.alignItems = 'center';
        box.style.gap = '8px';

        const status = document.createElement('div');
        status.textContent = (req.cumple ? 'Cumple' : 'No cumple');

        const dot = document.createElement('span');
        dot.className = 'siged-dot';
        dot.style.background = req.cumple ? '#16a34a' : '#b91c1c';

        box.appendChild(status);
        box.appendChild(dot);
        liE.appendChild(box);

        if (!req.cumple && req.detalle) {
          const det = document.createElement('div');
          det.className = 'siged-det';
          det.textContent = req.detalle;
          liE.appendChild(det);
        }

        ulE.appendChild(liE);
      });

      overlay.style.display = 'flex';
      overlay.setAttribute('aria-hidden','false');

      document.getElementById('siged-close').addEventListener('click', ()=>{
        fetch('/SIGED/public/index.php?action=conv_ack', {method:'POST', credentials:'same-origin'})
          .finally(()=>{
            overlay.style.display = 'none';
            overlay.setAttribute('aria-hidden','true');
          });
      });
    })
    .catch(err => console.error('conv_get error', err));
})();
</script>
<?php endif;  ?>




<body class="layout">
  <header class="topbar">
    <strong>SIGED</strong>
    <nav>
      <a href="/siged/public/index.php?action=pdf_demo">PDF demo</a>
      <a href="/siged/public/index.php?action=logout">Salir</a>
    </nav>
  </header>
  <main class="card">
    <h1>Bienvenido(a), <?= htmlspecialchars($user['nombre'] ?? 'Docente') ?></h1>
    <ul>
      <li>Expediente (próximo)</li>
      <li>Generación de documentos (TCPDF) (próximo)</li>
      <li>Convocatorias y requisitos (próximo)</li>
      <li>Tickets (próximo)</li>
    </ul>
    <p>
  <a href="/siged/public/index.php?action=sol_mis">Mis solicitudes</a> ·
  <a href="/siged/public/index.php?action=sol_nueva">Nueva solicitud</a>
</p>

  </main>
</body>
</html>
