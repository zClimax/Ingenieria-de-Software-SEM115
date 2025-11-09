<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php'; Session::start();
require_once __DIR__ . '/../utils/roles.php'; requireRole(['JEFE_DEPARTAMENTO']);
require_once __DIR__ . '/../config.php';

$pdo=DB::conn(); $u=Session::user(); $idJefe=(int)$u['id'];
$id=(int)($_GET['id'] ?? 0);

// seguridad: debe estar asignado a este jefe
$chk=$pdo->prepare("SELECT T.*
  , CONCAT(D.NOMBRE_DOCENTE,' ',D.APELLIDO_PATERNO_DOCENTE,' ',D.APELLIDO_MATERNO_DOCENTE) AS DOCENTE
  , D.CORREO
  FROM dbo.TICKETS T
  JOIN dbo.DOCENTE D ON D.ID_DOCENTE=T.ID_DOCENTE_GENERADOR
  WHERE T.ID_TICKET=:id AND T.ID_USUARIO_RESPONSABLE=:j");
$chk->execute([':id'=>$id, ':j'=>$idJefe]); $tk=$chk->fetch();
if(!$tk){ http_response_code(404); echo "Ticket no asignado a usted o inexistente."; exit; }

// comentarios
$c=$pdo->prepare("SELECT * FROM dbo.TICKET_COMENTARIO WHERE ID_TICKET=:id ORDER BY FECHA DESC");
$c->execute([':id'=>$id]); $com=$c->fetchAll();
?>
<!doctype html><html lang="es"><head><meta charset="utf-8">
<title>Atender Ticket #<?= (int)$id ?> | SIGED</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
body{margin:0;font-family:system-ui,Segoe UI,Roboto,Arial;background:#f8fafc}
.wrap{max-width:980px;margin:24px auto;padding:0 16px}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;box-shadow:0 8px 24px rgba(0,0,0,.06);padding:16px}
h1{margin:8px 0}
.badge{padding:2px 8px;border-radius:999px;border:1px solid #e5e7eb;background:#f3f4f6}
.meta{display:flex;gap:12px;flex-wrap:wrap;margin:6px 0 12px}
textarea,input[type=text],select{width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:10px}
.item{border-top:1px solid #eef2f7;padding:10px 0}
.small{color:#6b7280;font-size:12px}
.btn{background:#0b1a52;color:#fff;border:none;border-radius:10px;padding:10px 14px;cursor:pointer}
.grid{display:grid;grid-template-columns:2fr 1fr;gap:12px}
@media (max-width:860px){.grid{grid-template-columns:1fr}}
</style></head><body>
<div class="wrap"><div class="card">
  <a href="/SIGED/public/index.php?action=tkj_list">← Volver</a>
  <h1>Ticket #<?= (int)$id ?> — <?= htmlspecialchars($tk['TITULO']) ?></h1>
  <div class="meta">
    <span class="badge"><?= htmlspecialchars($tk['ESTATUS']) ?></span>
    <span>Prioridad: <strong><?= htmlspecialchars($tk['PRIORIDAD']) ?></strong></span>
    <span class="small">Docente: <?= htmlspecialchars($tk['DOCENTE']) ?> (<?= htmlspecialchars($tk['CORREO']) ?>)</span>
    <span class="small">Creado: <?= substr((string)$tk['FECHA_CREACION'],0,16) ?></span>
  </div>

  <div class="grid">
    <div>
      <h3>Comentarios</h3>
      <?php foreach($com as $cm): ?>
        <div class="item">
          <div class="small"><?= substr((string)$cm['FECHA'],0,16) ?> — <?= $cm['ID_USUARIO'] ? 'Jefe #'.$cm['ID_USUARIO'] : 'Docente #'.$cm['ID_DOCENTE'] ?></div>
          <div><?= nl2br(htmlspecialchars($cm['TEXTO'])) ?></div>
        </div>
      <?php endforeach; ?>

      <form action="/SIGED/public/index.php?action=tkj_comentar" method="post" style="margin-top:10px">
        <input type="hidden" name="id" value="<?= (int)$id ?>">
        <label>Nuevo comentario (visible para el docente)</label>
        <textarea name="texto" required></textarea>
        <div style="margin-top:8px"><button class="btn" type="submit">Enviar</button></div>
      </form>
    </div>

    <div>
      <h3>Acciones</h3>
      <form action="/SIGED/public/index.php?action=tkj_estado" method="post">
        <input type="hidden" name="id" value="<?= (int)$id ?>">
        <label>Estatus</label>
        <select name="estatus">
          <option value="ABIERTO"     <?= $tk['ESTATUS']==='ABIERTO'?'selected':'' ?>>ABIERTO</option>
          <option value="EN_REVISION" <?= $tk['ESTATUS']==='EN_REVISION'?'selected':'' ?>>EN_REVISION</option>
          <option value="CERRADO"     <?= $tk['ESTATUS']==='CERRADO'?'selected':'' ?>>CERRADO</option>
        </select>

        <label style="margin-top:8px">Prioridad</label>
        <select name="prioridad">
          <option value="BAJA"  <?= $tk['PRIORIDAD']==='BAJA'?'selected':'' ?>>BAJA</option>
          <option value="MEDIA" <?= $tk['PRIORIDAD']==='MEDIA'?'selected':'' ?>>MEDIA</option>
          <option value="ALTA"  <?= $tk['PRIORIDAD']==='ALTA'?'selected':'' ?>>ALTA</option>
        </select>

        <label style="margin-top:8px">Nota de resolución (si cierra)</label>
        <textarea name="resolucion" placeholder="Resumen de la solución/aprobación."></textarea>

        <div style="margin-top:10px"><button class="btn" type="submit">Aplicar cambios</button></div>
      </form>
    </div>
  </div>
</div></div>
</body></html>
