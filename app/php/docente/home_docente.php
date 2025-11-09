<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../utils/roles.php';
requireRole(['DOCENTE']); // protección
Session::start();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Interfaz de Datos Personales del Docente</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root{
      --azul:#0b1a52; --azul-2:#102a7a; --gris:#f3f4f6; --borde:#e5e7eb; --texto:#111827;
    }
    *{box-sizing:border-box}
    body{margin:0;font-family:system-ui,Segoe UI,Roboto,Arial;background:#f8fafc;color:var(--texto)}
    .layout{display:grid;grid-template-columns:260px 1fr;min-height:100vh}
    .sidebar{background:linear-gradient(180deg,#08123c,#0b1a52);color:#dbeafe;padding:16px}
    .brand{display:flex;align-items:center;gap:8px;margin:10px 0 24px 8px}
    .brand span{font-weight:800;letter-spacing:.5px}
    .menu a{display:flex;align-items:center;gap:10px;color:#dbeafe;text-decoration:none;padding:10px 12px;border-radius:10px;margin:4px 8px}
    .menu a.active,.menu a:hover{background:rgba(255,255,255,.12)}
    .avatar-mini{position:absolute;bottom:16px;left:16px;display:flex;align-items:center;gap:10px}
    .avatar-mini .pic{width:38px;height:38px;border-radius:999px;border:2px solid #fff;background:#0b1a52 url('') center/cover no-repeat}
    .content{padding:28px}
    .topbar{display:flex;justify-content:center;align-items:center;gap:8px;background:#fff;border:1px solid var(--borde);border-radius:12px;padding:10px 14px;width:fit-content;margin:0 auto 22px}
    .card{background:#fff;border:1px solid var(--borde);border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,.05)}
    .card .section{padding:18px 24px;border-bottom:1px solid var(--borde)}
    .section:last-child{border-bottom:0}
    .title-sec{background:#eef2ff;color:#0b1a52;text-align:center;padding:8px;border-radius:30px;margin:12px auto;width:60%}
    .id-row{display:flex;gap:20px;align-items:center}
    .avatar{width:110px;height:110px;border:2px dashed #cbd5e1;border-radius:16px;background:#f8fafc;display:flex;align-items:center;justify-content:center;font-size:54px;color:#94a3b8}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}
    .pill{background:#f3f4f6;border:1px solid var(--borde);border-radius:12px;padding:10px 14px}
    .bar-wrap{background:#eef2ff;border-radius:20px;height:22px;display:flex;align-items:center;padding:2px}
    .bar{height:18px;border-radius:18px;background:linear-gradient(90deg,#0b1a52,#102a7a);width:0%}
    .bar-meta{display:flex;justify-content:center;gap:10px;margin-top:8px;font-weight:600}
    .btn{display:inline-block;background:#fff;border:1px solid var(--borde);padding:10px 14px;border-radius:10px;cursor:pointer}
    .btn.primary{background:#0b1a52;color:#fff;border-color:#0b1a52}
    @media (max-width:980px){.layout{grid-template-columns:1fr}.sidebar{display:none}.title-sec{width:90%}.grid{grid-template-columns:1fr}}
  </style>
</head>
<body>

<!-- Modal Convocatoria -->
<div id="convModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:9999;align-items:center;justify-content:center">
  <div style="background:#fff;max-width:720px;width:92%;border-radius:14px;padding:16px;border:1px solid #e5e7eb;box-shadow:0 8px 24px rgba(0,0,0,.12)">
    <h2 style="margin:6px 0 8px">Convocatoria activa</h2>
    <div id="convMeta" style="color:#374151;font-size:14px;margin-bottom:8px"></div>
    <div>
      <strong>Requisitos</strong>
      <ul id="reqList" style="margin-top:6px;padding-left:18px"></ul>
    </div>
    <div id="convMsg" style="margin-top:8px;color:#111827"></div>
    <div style="margin-top:12px;text-align:right">
      <button id="btnConvOk" style="background:#0b1a52;color:#fff;border:none;border-radius:10px;padding:10px 14px;cursor:pointer">Entendido</button>
    </div>
  </div>
</div>

<script>
(function(){
  const modal   = document.getElementById('convModal');
  const meta    = document.getElementById('convMeta');
  const ul      = document.getElementById('reqList');
  const msg     = document.getElementById('convMsg');
  const btn     = document.getElementById('btnConvOk');
  let convId    = 0;

  function openModal(){ modal.style.display='flex'; }
  function closeModal(){ modal.style.display='none'; }

  async function loadConv(){
    try{
      const r = await fetch('/SIGED/public/index.php?action=conv_get', {credentials:'same-origin'});
      const j = await r.json();
      if(!j.ok){ console.warn(j); return; }
      if(!j.mostrar_modal){ return; } // nada que mostrar

      convId = Number(j.convocatoria?.id || 0);
      const clave = j.convocatoria?.clave || '';
      const nombre= j.convocatoria?.nombre || '';
      const ini   = (j.convocatoria?.fecha_ini || '').toString().slice(0,10);
      const fin   = (j.convocatoria?.fecha_fin || '').toString().slice(0,10);

      meta.textContent = `${clave ? clave + ' — ' : ''}${nombre} (${ini} a ${fin})`;
      ul.innerHTML = '';
      (j.requisitos || []).forEach(rq=>{
        const li = document.createElement('li');
        li.textContent = `${rq.nombre} ${rq.cumple ? '✓' : '✕'}`;
        ul.appendChild(li);
      });
      msg.textContent = j.mensaje || '';
      openModal();
    }catch(e){ console.error(e); }
  }

  btn?.addEventListener('click', async ()=>{
    try{
      if(!convId){ closeModal(); return; }
      const fd = new FormData(); fd.append('id_convocatoria', String(convId));
      const r = await fetch('/SIGED/public/index.php?action=conv_ack', { method:'POST', body:fd, credentials:'same-origin' });
      const j = await r.json();
      if(j && j.ok){ closeModal(); }
    }catch(e){ console.error(e); closeModal(); }
  });

  // Dispara al cargar el home
  document.addEventListener('DOMContentLoaded', loadConv);
})();
</script>

  <div class="layout">
    <aside class="sidebar">
      <div class="brand"><span style="background:#fff;color:#0b1a52;border-radius:8px;padding:2px 6px;font-weight:900">S</span><span>SIGED</span></div>
      <nav class="menu">
  <a href="/SIGED/public/index.php?action=sol_mis">Generador de actas</a>
  <a href="/SIGED/public/index.php?action=home_docente" class="active">Usuario</a>
  <a href="/SIGED/public/index.php?action=tk_list">Tickets</a>
  <a href="/siged/public/index.php?action=doc_firma">Mi firma</a>
    </nav>
      <div class="avatar-mini">
        <div class="pic"></div>
        <div style="font-size:12px" id="miniName">Docente</div>
      </div>
    </aside>

    <main class="content">
      <div class="topbar"><strong>SIGED</strong><span style="opacity:.6">| Interfaz de Datos Personales del Docente</span>
        <div style="margin-left:18px"><a href="/SIGED/public/index.php?action=logout" class="btn">Salir</a></div>
      </div>

      <div class="card">
        <div class="section">
          <div class="title-sec">Identificación</div>
          <div class="id-row">
            <div class="avatar">👤</div>
            <div>
              <div id="docNombre" style="font-weight:700;font-size:18px">—</div>
              <div id="docRFC"    style="opacity:.8;margin-top:6px">—</div>
              <div id="docCorreo" style="opacity:.8;margin-top:6px">—</div>
              <div id="docDepto"  style="opacity:.8;margin-top:6px">—</div>
            </div>
          </div>
        </div>

        <div class="section">
          <div class="title-sec">Datos personales</div>
          <div class="grid">
            <div class="pill"><strong>CLAVE DE EMPLEADO:</strong> <span id="docClave">—</span></div>
            <div class="pill"><strong>NSS:</strong> <span id="docNss">—</span></div>
            <div class="pill"><strong>FECHA DE INGRESO:</strong> <span id="docIngreso">—</span></div>
            <div class="pill"><strong>RFC:</strong> <span id="docRfc">—</span></div>
            <div class="pill"><strong>MATRÍCULA:</strong> <span id="docMatricula">—</span></div>
          </div>
        </div>
      <div class = "section">
        
      </div>
        <div class="section">
          <div class="title-sec">Barra de progreso</div>
          <div class="bar-wrap"><div class="bar" id="bar"></div></div>
          <div class="bar-meta"><span id="ptsLabel">0 pts.</span> <span id="pctLabel">0%</span></div>
          <div style="margin-top:10px">
          <button class="btn" id="btnHist">Histórico de convocatorias</button>
        <script>
        document.getElementById('btnHist').addEventListener('click', ()=>{
         location.href = '/SIGED/public/index.php?action=doc_hist';
        });
        </script>

          </div>
        </div>
      </div>
    </main>
  </div>
  <script>
  (function(){
    const base = location.pathname.includes('/public/index.php')
  ? '/SIGED/public/index.php'  // si tu carpeta es SIGED (mayúsculas)
  : (location.pathname.replace(/index\.php.*$/,'index.php'));

fetch(base + '?action=doc_home_data', { credentials:'same-origin' })
      .then(r=>r.json())
      .then(data=>{
        if(!data || !data.ok) { console.error(data); return; }

        const d  = data.docente || {};
        const pr = data.progreso || {puntos:0,max:300,porcentaje:0};

        document.getElementById('miniName').textContent = d.nombre || 'Docente';

        // Identificación
        document.getElementById('docNombre').textContent = d.nombre || '—';
        document.getElementById('docRFC').textContent    = d.rfc ? ('RFC: '+d.rfc) : '—';
        document.getElementById('docCorreo').textContent = d.correo || '—';
        document.getElementById('docDepto').textContent  = d.departamento || '—';

        // Datos personales
        document.getElementById('docClave').textContent    = d.clave_empleado || '—';
        document.getElementById('docNss').textContent      = d.nss || '—';
        document.getElementById('docIngreso').textContent  = d.fecha_ingreso || '—';
        document.getElementById('docRfc').textContent      = d.rfc || '—';
        document.getElementById('docMatricula').textContent= d.matricula || '—';

        // Barra
        const pct = Math.max(0, Math.min(100, pr.porcentaje|0));
        const bar = document.getElementById('bar');
        bar.style.width = pct + '%';
        document.getElementById('ptsLabel').textContent = (pr.puntos||0) + ' pts.';
        document.getElementById('pctLabel').textContent = pct + '%';

    // Botón histórico
    document.getElementById('btnHist').addEventListener('click', ()=>{
    location.href = '/SIGED/public/index.php?action=doc_hist';
    });
      })
      .catch(err=>console.error(err));
  })();
  </script>
</body>
</html>
