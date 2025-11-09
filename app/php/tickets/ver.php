<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php'; Session::start();
require_once __DIR__ . '/../utils/roles.php'; requireRole(['DOCENTE']);
require_once __DIR__ . '/../config.php';

$pdo = DB::conn();
$u   = Session::user();
$idUsuario = (int)$u['id'];
$id = (int)($_GET['id'] ?? 0);

// seguridad + traer responsable y su depto
$sql = "
  SELECT T.*,
         D.ID_DOCENTE,
         URESP.NOMBRE_USUARIO         AS JEFE_NOMBRE,
         URESP.ID_USUARIO             AS JEFE_ID,
         DP.NOMBRE_DEPARTAMENTO       AS JEFE_DEPTO
  FROM dbo.TICKETS T
  JOIN dbo.DOCENTE D ON D.ID_USUARIO = :u
  LEFT JOIN dbo.USUARIOS URESP ON URESP.ID_USUARIO = T.ID_USUARIO_RESPONSABLE
  LEFT JOIN dbo.DEPARTAMENTO DP ON DP.ID_DEPARTAMENTO = URESP.ID_DEPARTAMENTO
  WHERE T.ID_TICKET = :id
    AND T.ID_DOCENTE_GENERADOR = D.ID_DOCENTE
";
$chk = $pdo->prepare($sql);
$chk->execute([':u'=>$idUsuario, ':id'=>$id]);
$tk = $chk->fetch();

if (!$tk) { http_response_code(404); echo "Ticket no encontrado"; exit; }

$asignadoA = '—';
if (!empty($tk['JEFE_NOMBRE'])) {
  $asignadoA = $tk['JEFE_NOMBRE'] . (!empty($tk['JEFE_DEPTO']) ? ' · ' . $tk['JEFE_DEPTO'] : '');
} elseif (!empty($tk['ID_USUARIO_RESPONSABLE'])) {
  $asignadoA = 'Usuario #'.$tk['ID_USUARIO_RESPONSABLE'];
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Ticket #<?= (int)$id ?> | SIGED</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    body{margin:0;font-family:system-ui,Segoe UI,Roboto,Arial;background:#f8fafc}
    .wrap{max-width:900px;margin:24px auto;padding:0 16px}
    .card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;box-shadow:0 8px 24px rgba(0,0,0,.06);padding:16px}
    h1{margin:8px 0}
    .badge{padding:2px 8px;border-radius:999px;border:1px solid #e5e7eb;background:#f3f4f6}
    .meta{display:flex;gap:12px;flex-wrap:wrap;margin:6px 0 12px}
    textarea{width:100%;min-height:100px;padding:10px;border:1px solid #e5e7eb;border-radius:10px}
    .item{border-top:1px solid #eef2f7;padding:10px 0}
    .small{color:#6b7280;font-size:12px}
    .btn{background:#0b1a52;color:#fff;border:none;border-radius:10px;padding:10px 14px;cursor:pointer}
    a{color:#0b1a52}
  </style>
</head>
<body>
<div class="wrap"><div class="card">
  <!-- DOC_VER_v2 -->
  <a href="/SIGED/public/index.php?action=tk_list">← Volver</a>
  <h1>Ticket #<?= (int)$id ?> — <?= htmlspecialchars($tk['TITULO']) ?></h1>
  <div class="meta">
    <span class="badge"><?= htmlspecialchars($tk['ESTATUS']) ?></span>
    <span>Prioridad: <strong><?= htmlspecialchars($tk['PRIORIDAD']) ?></strong></span>
    <span class="small">Creado: <?= substr((string)$tk['FECHA_CREACION'],0,16) ?></span>
    <span class="small">Asignado a: <strong><?= htmlspecialchars($asignadoA) ?></strong></span>
  </div>

  <p><?= nl2br(htmlspecialchars($tk['DESCRIPCION'])) ?></p>

  <h3>Comentarios</h3>
  <div>
    <?php
      $c = $pdo->prepare("SELECT * FROM dbo.TICKET_COMENTARIO WHERE ID_TICKET=:id ORDER BY FECHA DESC");
      $c->execute([':id'=>$id]); $com = $c->fetchAll();
      foreach($com as $cm):
    ?>
      <div class="item">
        <div class="small">
          <?= substr((string)$cm['FECHA'],0,16) ?> —
          <?= $cm['ID_USUARIO'] ? 'Jefe #'.$cm['ID_USUARIO'] : 'Docente #'.$cm['ID_DOCENTE'] ?>
        </div>
        <div><?= nl2br(htmlspecialchars($cm['TEXTO'])) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($tk['ESTATUS'] !== 'CERRADO'): ?>
    <form action="/SIGED/public/index.php?action=tk_comentar" method="post" style="margin-top:12px">
      <input type="hidden" name="id" value="<?= (int)$id ?>">
      <textarea name="texto" required placeholder="Escribe un comentario para el Jefe de Departamento..."></textarea>
      <div style="margin-top:8px;display:flex;gap:8px">
        <button class="btn" type="submit">Enviar comentario</button>
      </div>
    </form>
  <?php else: ?>
    <div class="badge">Ticket cerrado<?= $tk['FECHA_CIERRE'] ? ' — '.substr((string)$tk['FECHA_CIERRE'],0,16) : '' ?></div>
    <?php if (!empty($tk['RESOLUCION'])): ?>
      <span class="small">
  Asignado a: <strong><?= htmlspecialchars($tk['JEFE_NOMBRE'] ?? '—') ?></strong>
  <?= $tk['JEFE_DEPTO'] ? ' · '.htmlspecialchars($tk['JEFE_DEPTO']) : '' ?>
  <?= $tk['JEFE_CORREO'] ? ' · <a href="mailto:'.htmlspecialchars($tk['JEFE_CORREO']).'">'.htmlspecialchars($tk['JEFE_CORREO']).'</a>' : '' ?>
</span>

    <?php endif; ?>
  <?php endif; ?>

</div></div>
</body>
</html>
