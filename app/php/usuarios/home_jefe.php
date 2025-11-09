<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';

Session::start();
$user = Session::user();

// Traer firma vigente y nombre completo desde BD
$pdo = DB::conn();
$uId = (int)($user['id'] ?? 0);
$firmaUrl = '';
$displayName = trim((string)($user['nombre'] ?? 'Jefe de Departamento'));

if ($uId > 0) {
  $st = $pdo->prepare("
    SELECT NOMBRE_COMPLETO, RUTA_FIRMA
    FROM [SIGED].[dbo].[USUARIOS]
    WHERE ID_USUARIO = :id
  ");
  $st->execute([':id'=>$uId]);
  if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
    $firmaUrl = (string)($row['RUTA_FIRMA'] ?? '');
    $full = trim((string)($row['NOMBRE_COMPLETO'] ?? ''));
    if ($full !== '') { $displayName = $full; }
  }
}

// Mensaje flash simple (por querystring)
$msg = $_GET['msg'] ?? '';
function msgText(string $m): string {
  return match($m) {
    'firma_ok'     => 'Firma actualizada correctamente.',
    'firma_tipo'   => 'Formato no válido (usa PNG o JPG).',
    'firma_pesada' => 'Archivo demasiado grande (máx. 2 MB).',
    'firma_error'  => 'No se pudo recibir el archivo.',
    default        => ''
  };
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>SIGED · Jefe de Departamento</title>
  <link rel="stylesheet" href="/siged/app/css/styles.css">
  <script defer src="/siged/app/js/main.js"></script>
  <style>
    .wrap { max-width: 1100px; margin: 2rem auto; }
    .kpi-grid { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 12px; margin-bottom: 16px; }
    .kpi { border: 1px solid #eee; border-radius: 10px; padding: 10px; background: #fff; box-shadow: 0 2px 8px rgba(0,0,0,.03); }
    .kpi .label { font-size: 12px; color: #666; }
    .kpi .value { font-size: 22px; font-weight: 700; }
    .grid-2 { display: grid; grid-template-columns: 1.2fr .8fr; gap: 16px; margin-bottom: 16px; }
    .card-plain { border: 1px solid #eee; border-radius: 10px; padding: 1rem; background: #fff; box-shadow: 0 2px 8px rgba(0,0,0,.03); }
    table.tbl { width:100%; border-collapse: collapse; }
    table.tbl th, table.tbl td { text-align:left; padding:.5rem; border-bottom:1px solid #f3f4f6; }
    #trend { height: 140px; border:1px solid #eee; border-radius:8px; padding:8px; }
    @media (max-width: 920px) { .kpi-grid { grid-template-columns: repeat(2, minmax(0,1fr)); } .grid-2 { grid-template-columns: 1fr; } }
  </style>
</head>
<body class="layout">
  <header class="topbar">
    <strong>SIGED</strong>
    <nav>
      <a href="/siged/public/index.php?action=pdf_demo">PDF demo</a>
      <a href="/siged/public/index.php?action=tkj_list">tickets</a>
      <a href="/siged/public/index.php?action=logout">Salir</a>
    </nav>
  </header>

  <main class="wrap">
    <section class="card-plain" style="margin-bottom:16px">
    <h1 style="margin:.25rem 0 0">Bienvenido(a), <?= htmlspecialchars($displayName) ?></h1>
      <div style="color:#666">Panel del Jefe</div>
      <?php if ($msgTxt = msgText($msg)): ?>
        <div class="kpi" style="margin-top:10px;background:#f9fafb">
          <div class="label">Aviso</div>
          <div class="value" style="font-size:14px"><?= htmlspecialchars($msgTxt) ?></div>
        </div>
      <?php endif; ?>
    </section>

    <!-- Firma -->
    <section class="card-plain" style="margin-bottom:16px; display:grid; grid-template-columns:1fr 1fr; gap:16px;">
      <div>
        <h3 style="margin-top:0">Tu firma</h3>
        <div style="border:1px dashed #ddd; border-radius:8px; padding:10px; text-align:center; min-height:120px; display:flex; align-items:center; justify-content:center;">
          <?php if ($firmaUrl): ?>
            <img src="<?= htmlspecialchars($firmaUrl) ?>" alt="Firma" style="max-width:100%; max-height:100px; object-fit:contain">
          <?php else: ?>
            <div style="color:#999">Sin firma cargada</div>
          <?php endif; ?>
        </div>
        <div style="font-size:12px;color:#666;margin-top:8px">
          Recomendado: PNG con fondo transparente, aprox. 900×300 px. Peso máx. 2 MB.
        </div>
      </div>
      <div>
        <h3 style="margin-top:0">Actualizar firma</h3>
        <form method="post" action="/siged/public/index.php?action=jefe_firma_guardar" enctype="multipart/form-data">
          <input type="file" name="firma" accept="image/png, image/jpeg" required>
          <div style="margin-top:8px">
            <button type="submit">Guardar firma</button>
          </div>
        </form>
      </div>
    </section>

    <!-- KPIs -->
    <section class="kpi-grid" id="kpis"></section>

    <!-- Tendencia + Tickets -->
    <section class="grid-2">
      <div class="card-plain">
        <h3 style="margin-top:0">Tendencia de decisiones (30 días)</h3>
        <div id="trend"></div>
      </div>
      <div class="card-plain">
        <h3 style="margin-top:0">Tickets</h3>
        <ul id="tickets" style="list-style:none;padding:0;margin:0;display:grid;gap:6px"></ul>
      </div>
    </section>

    <!-- Backlog -->
    <section class="card-plain">
      <h3 style="margin-top:0">Backlog prioritario (pendientes)</h3>
      <table id="backlog" class="tbl">
        <thead>
          <tr>
            <th>Solicitud</th>
            <th>Tipo documento</th>
            <th>Estado</th>
            <th>Días pendientes</th>
            <th>Enviado</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
      <div style="margin-top:.5rem">
        <a href="/siged/public/index.php?action=jefe_bandeja">Ir a Bandeja</a>
      </div>
    </section>
  </main>

  <!-- DASHBOARD JS (igual que tenías, sin cambios de lógica) -->
  <script>
  (async function(){
    const kEl = document.getElementById('kpis');
    function aviso(msg){
      const d = document.createElement('div'); d.className = 'kpi';
      d.innerHTML = `<div class="label">Aviso</div><div class="value" style="font-size:14px">${msg}</div>`;
      kEl.appendChild(d);
    }
    let data;
    try {
      const res = await fetch('?action=jefe_home_data', {credentials:'same-origin'});
      const text = await res.text();
      if (!res.ok) { aviso(`HTTP ${res.status}: ${text.slice(0,120)}`); return; }
      data = JSON.parse(text);
      if (data.error){ aviso(data.error); return; }
    } catch(e){ aviso('No se pudo cargar el dashboard'); console.error(e); return; }

    const k = data.kpis || {};
    const kpis = [
      {label:'Aprobadas', value:k.aprobadas ?? '0'},
      {label:'Pendientes', value:k.pendientes ?? '0'},
      {label:'Rechazadas', value:k.rechazadas ?? '0'},
      {label:'Pend. vencidas', value:k.pendientes_vencidas ?? '0'},
      {label:'Tk abiertos', value:k.tickets_abiertos ?? '0'},
      {label:'Tk en curso', value:k.tickets_en_curso ?? '0'},
      {label:'Tk cerrados', value:k.tickets_cerrados ?? '0'},
      {label:'Convocatoria', value:k.convocatoria || '—'}
    ];
    kpis.forEach(x=>{
      const d = document.createElement('div'); d.className = 'kpi';
      d.innerHTML = `<div class="label">${x.label}</div><div class="value">${x.value}</div>`;
      kEl.appendChild(d);
    });

    const t = data.tendencia_30d || [];
    const trend = document.getElementById('trend');
    const W = trend.clientWidth || 560, H = trend.clientHeight || 140, pad = 18;
    if (t.length) {
      const max = Math.max(1, ...t.map(r => +r.decisiones || 0));
      const xs = t.map((r,i) => pad + (i*(W-2*pad)/Math.max(1,t.length-1)));
      const ys = t.map(r => H-pad - ((+r.decisiones||0)/max)*(H-2*pad));
      trend.innerHTML =
        `<svg width="${W}" height="${H}">
          <polyline points="${xs.map((x,i)=>`${x},${ys[i]}`).join(' ')}" fill="none" stroke="#5b8def" stroke-width="2"/>
          <line x1="${pad}" y1="${H-pad}" x2="${W-pad}" y2="${H-pad}" stroke="#eee"/>
          <line x1="${pad}" y1="${pad}" x2="${pad}" y2="${H-pad}" stroke="#eee"/>
        </svg>`;
    } else {
      trend.innerHTML = `<div style="color:#999">Sin decisiones en 30 días.</div>`;
    }

    const tk = document.getElementById('tickets');
    tk.innerHTML = `
      <li>Abrs: <strong>${k.tickets_abiertos ?? 0}</strong></li>
      <li>En curso: <strong>${k.tickets_en_curso ?? 0}</strong></li>
      <li>Cerrados: <strong>${k.tickets_cerrados ?? 0}</strong></li>
    `;

    const tb = document.querySelector('#backlog tbody');
    (data.backlog || []).forEach(r=>{
      const tr = document.createElement('tr');
      const fecha = (r.FECHA_ENVIO || r.FECHA_CREACION || '').toString().slice(0,10);
      tr.innerHTML = `
        <td>${r.ID_SOLICITUD}</td>
        <td>${r.TIPO_DOCUMENTO || '—'}</td>
        <td>${r.ESTADO}</td>
        <td style="font-weight:700">${r.dias_pendientes}</td>
        <td>${fecha}</td>
      `;
      tb.appendChild(tr);
    });
    if ((data.backlog || []).length === 0) {
      const tr = document.createElement('tr');
      tr.innerHTML = `<td colspan="5" style="color:#999">Sin pendientes.</td>`;
      tb.appendChild(tr);
    }
  })();
  </script>
</body>
</html>
