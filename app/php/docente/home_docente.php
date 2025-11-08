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
  <div class="layout">
    <aside class="sidebar">
      <div class="brand"><span style="background:#fff;color:#0b1a52;border-radius:8px;padding:2px 6px;font-weight:900">S</span><span>SIGED</span></div>
      <nav class="menu">
        <a href="#" class="active">Generador de actas</a>
        <a href="#">Usuario</a>
        <a href="#">Tickets</a>
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

        <div class="section">
          <div class="title-sec">Barra de progreso</div>
          <div class="bar-wrap"><div class="bar" id="bar"></div></div>
          <div class="bar-meta"><span id="ptsLabel">0 pts.</span> <span id="pctLabel">0%</span></div>
          <div style="margin-top:10px">
            <button class="btn" id="btnHist">Histórico de convocatorias</button>
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

        // Botón histórico (placeholder)
        document.getElementById('btnHist').addEventListener('click', ()=>{
          alert('Próximamente: histórico de convocatorias.');
        });
      })
      .catch(err=>console.error(err));
  })();
  </script>
</body>
</html>
