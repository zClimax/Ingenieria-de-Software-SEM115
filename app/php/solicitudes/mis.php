<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';

Session::start();
$pdo  = DB::conn();
$user = (array)(Session::user() ?? []);
$uid  = (int)($user['id'] ?? 0);
if ($uid <= 0) { http_response_code(403); exit('Sesión inválida'); }

// ===== Helpers
function corrOpen(PDO $pdo, int $idSol): bool {
  $q = $pdo->prepare("SELECT 1
                      FROM dbo.DOC_CORRECCION
                      WHERE ID_SOLICITUD=:id AND ESTATUS IN('ABIERTA','EN_EDICION')");
  $q->execute([':id'=>$idSol]);
  return (bool)$q->fetchColumn();
}



$sql = "
SELECT
  S.ID_SOLICITUD         AS id,
  S.TIPO_DOCUMENTO       AS tipo,
  S.ESTADO               AS estado,
  S.FECHA_CREACION       AS f_crea,
  S.FECHA_ENVIO          AS f_env,
  S.FECHA_DECISION       AS f_dec,
  S.FOLIO                AS folio,
  S.COMENTARIO_JEFE      AS comentario
FROM [SIGED].[dbo].[SOLICITUD_DOCUMENTO] S
JOIN [SIGED].[dbo].[DOCENTE] D   ON D.ID_DOCENTE  = S.ID_DOCENTE
JOIN [SIGED].[dbo].[USUARIOS] U  ON U.ID_USUARIO  = D.ID_USUARIO
WHERE U.ID_USUARIO = :uid
ORDER BY S.ID_SOLICITUD DESC";
$st = $pdo->prepare($sql);
$st->execute([':uid' => $uid]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

function badgeEstado(string $e): array {
  // text, bg, fg
  return match (strtoupper($e)) {
    'APROBADA'  => ['APROBADA',  '#e8fff3', '#0a7c46'],
    'ENVIADA'   => ['ENVIADA',   '#e9f2ff', '#0b5ed7'],
    'RECHAZADA' => ['RECHAZADA', '#ffecec', '#c62828'],
    default     => ['BORRADOR',  '#f3f4f6', '#374151'],
  };
}
function chipTipo(string $t): array {
  return [strtoupper($t), '#eef2ff', '#4338ca']; // morado tenue por default
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>SIGED · Mis solicitudes</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/siged/app/css/styles.css">
  <style>
    .wrap{max-width:1100px;margin:2rem auto}
    .card{border:1px solid #eee;border-radius:12px;background:#fff;box-shadow:0 2px 10px rgba(0,0,0,.04)}
    .head{display:flex;justify-content:space-between;align-items:center;padding:1rem 1.25rem;border-bottom:1px solid #f1f5f9}
    .title{font-size:28px;margin:0}
    .body{padding:1rem 1.25rem}
    .stack{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
    .btn{display:inline-block;padding:.5rem .75rem;border-radius:10px;text-decoration:none;border:1px solid #e5e7eb;background:#0b5ed7;color:#fff}
    .btn-sec{display:inline-block;padding:.5rem .75rem;border-radius:10px;text-decoration:none;border:1px solid #e5e7eb;background:#f9fafb}
    .pill{padding:.35rem .6rem;border-radius:999px;border:1px solid #e5e7eb;background:#fff;cursor:pointer;font-size:13px}
    .pill.active{background:#0b5ed7;color:#fff;border-color:#0b5ed7}
    .search{padding:.5rem .75rem;border:1px solid #e5e7eb;border-radius:10px;min-width:260px}
    table{width:100%;border-collapse:separate;border-spacing:0}
    th,td{padding:.65rem .5rem;border-bottom:1px solid #f1f5f9;vertical-align:top}
    th{text-align:left;color:#64748b;font-weight:600}
    tr:hover td{background:#fafafa}
    .badge{display:inline-block;padding:.2rem .5rem;border-radius:999px;font-size:12px;font-weight:600}
    .chip{display:inline-block;padding:.15rem .45rem;border-radius:8px;font-size:12px;font-weight:600;border:1px solid transparent}
    .actions a{margin-right:8px}
    .muted{color:#6b7280;font-size:12px}
    .empty{padding:1.25rem;border:1px dashed #e5e7eb;border-radius:10px;text-align:center;color:#6b7280}
    .comment{background:#fff7ed;border:1px solid #ffedd5;color:#7c2d12;padding:.5rem .75rem;border-radius:8px}
    .link{color:#0b5ed7;text-decoration:none}
    .nowrap{white-space:nowrap}
  </style>
</head>
<body class="layout">
  <header class="topbar">
    <strong>SIGED</strong>
    <nav>
      <a href="/siged/public/index.php?action=home_docente">Inicio</a>
      <a href="/siged/public/index.php?action=logout">Salir</a>
    </nav>
  </header>

  <main class="wrap">
    <section class="card">
      <div class="head">
        <h1 class="title">Mis solicitudes</h1>
        <div class="stack">
          <input id="q" type="search" class="search" placeholder="Buscar por folio, tipo o estado…">
          <a class="btn" href="/siged/public/index.php?action=sol_nueva">+ Nueva solicitud</a>
        </div>
      </div>
      <div class="body">
        <div class="stack" style="margin-bottom:10px">
          <span class="pill active" data-filter="TODOS">Todos</span>
          <span class="pill" data-filter="BORRADOR">Borrador</span>
          <span class="pill" data-filter="ENVIADA">Enviada</span>
          <span class="pill" data-filter="APROBADA">Aprobada</span>
          <span class="pill" data-filter="RECHAZADA">Rechazada</span>
        </div>

        <?php if (!$rows): ?>
          <div class="empty">
            Aún no tienes solicitudes. <a class="link" href="/siged/public/index.php?action=sol_nueva">Crear la primera</a>.
          </div>
        <?php else: ?>
          <div class="table-wrap">
            <table id="tbl">
              <thead>
                <tr>
                  <th>ID / Folio</th>
                  <th>Tipo</th>
                  <th>Estado</th>
                  <th>Fechas</th>
                  <th class="nowrap">Acciones</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($rows as $r):
                $id     = (int)$r['id'];
                $folio  = trim((string)($r['folio'] ?? ''));
                [$tText,$tBg,$tFg] = chipTipo((string)$r['tipo']);
                [$eText,$eBg,$eFg] = badgeEstado((string)$r['estado']);
                $fC = $r['f_crea'] ? substr((string)$r['f_crea'],0,19) : '—';
                $fE = $r['f_env']  ? substr((string)$r['f_env'],0,19)  : '—';
                $fD = $r['f_dec']  ? substr((string)$r['f_dec'],0,19)  : '—';
                $coment = trim((string)($r['comentario'] ?? ''));
              ?>
                <tr data-status="<?= htmlspecialchars(strtoupper($eText)) ?>">
                  <td>
                    <div><strong>#<?= $id ?></strong> <?= $folio ? '· Folio: '.htmlspecialchars($folio) : '' ?></div>
                    <div class="muted">Creada: <?= htmlspecialchars($fC) ?></div>
                  </td>
                  <td>
                    <span class="chip" style="background:<?= $tBg ?>;color:<?= $tFg ?>;border-color:rgba(67,56,202,.15)"><?= htmlspecialchars($tText) ?></span>
                  </td>
                  <td>
                    <span class="badge" style="background:<?= $eBg ?>;color:<?= $eFg ?>"><?= htmlspecialchars($eText) ?></span>
                    <?php if (strtoupper($eText)==='ENVIADA'): ?>
                      <div class="muted">Enviada: <?= htmlspecialchars($fE) ?></div>
                    <?php elseif (strtoupper($eText)==='APROBADA'): ?>
                      <div class="muted">Decidida: <?= htmlspecialchars($fD) ?></div>
                    <?php elseif (strtoupper($eText)==='RECHAZADA'): ?>
                      <div class="muted">Decidida: <?= htmlspecialchars($fD) ?></div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div>Envío: <?= htmlspecialchars($fE) ?></div>
                    <div>Decisión: <?= htmlspecialchars($fD) ?></div>
                  </td>
                  <td class="actions nowrap">
                    <?php if (strtoupper($eText)==='BORRADOR' || strtoupper($eText)==='RECHAZADA'): ?>
                      <a class="link" href="/siged/public/index.php?action=sol_editar&id=<?= $id ?>">Editar</a>
                      <a class="link" href="/siged/public/index.php?action=sol_subir&id=<?= $id ?>">Subir</a>
                      <a class="link" href="/siged/public/index.php?action=sol_enviar&id=<?= $id ?>" onclick="return confirm('¿Enviar la solicitud #<?= $id ?>?');">Enviar</a>
                      <?php if ($coment): ?>
                        <a class="link" href="#" onclick="toggleCmt(<?= $id ?>);return false;">Ver comentario</a>
                      <?php endif; ?>
                    <?php elseif (strtoupper($eText)==='ENVIADA'): ?>
                      <a class="link" href="/siged/public/index.php?action=sol_editar&id=<?= $id ?>">Editar</a>
                    <?php elseif (strtoupper($eText)==='APROBADA'): ?>
                      <a class="link" href="/siged/public/index.php?action=doc_pdf&id=<?= $id ?>">Generar / Ver PDF</a>
                      <?php if (!corrOpen($pdo, $id)): ?>
            · <a href="/siged/public/index.php?action=sol_corr_new&id=<?= $id ?>">Solicitar corrección</a>
          <?php else: ?>
            · <span style="background:#FEF3C7;color:#92400E;padding:2px 6px;border-radius:6px;font-size:12px">Corrección en proceso</span>
          <?php endif; ?>
                    <?php endif; ?>   
                  </td>
                </tr>
                <?php if ($coment): ?>
                <tr id="cmt-<?= $id ?>" style="display:none">
                  <td colspan="5">
                    <div class="comment"><strong>Comentario del Jefe:</strong> <?= nl2br(htmlspecialchars($coment)) ?></div>
                  </td>
                </tr>
                <?php endif; ?>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </section>
  </main>

  <script>
    // Filtro por estado
    const pills = document.querySelectorAll('.pill');
    const rows  = document.querySelectorAll('#tbl tbody tr[data-status]');
    pills.forEach(p => p.addEventListener('click', () => {
      pills.forEach(x => x.classList.remove('active'));
      p.classList.add('active');
      const f = p.getAttribute('data-filter');
      rows.forEach(r => {
        if (!f || f === 'TODOS') { r.style.display = ''; showCmtRow(r, false); return; }
        r.style.display = (r.getAttribute('data-status') === f) ? '' : 'none';
        showCmtRow(r, false);
      });
    }));

    // Búsqueda simple
    const q = document.getElementById('q');
    if (q) q.addEventListener('input', () => {
      const term = q.value.toLowerCase();
      rows.forEach(r => {
        const txt = r.innerText.toLowerCase();
        r.style.display = txt.includes(term) ? '' : 'none';
        showCmtRow(r, false);
      });
    });

    function showCmtRow(mainRow, show) {
      const id = mainRow.querySelector('td strong')?.textContent?.replace('#','');
      const cmt = document.getElementById('cmt-'+id);
      if (cmt) cmt.style.display = show ? '' : 'none';
    }
    window.toggleCmt = function(id){
      const c = document.getElementById('cmt-'+id);
      if (c) c.style.display = (c.style.display === 'none' || !c.style.display) ? '' : 'none';
    }
  </script>
</body>
</html>
