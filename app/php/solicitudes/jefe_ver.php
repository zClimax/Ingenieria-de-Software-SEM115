<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';
requireRole(['JEFE_DEPARTAMENTO']);
$pdo = DB::conn();

$S=Config::MAP['SOLICITUD']; $E=Config::MAP['EVID']; $D=Config::MAP['DOCENTE'];
$id = (int)($_GET['id'] ?? 0);
if ($id<=0) { die('ID inválido'); }

$sol = $pdo->query("SELECT ".$S['ID']." AS id, ".$S['TIPO']." AS tipo, ".$S['ESTADO']." AS estado, ".$S['DOC']." AS id_doc
                    FROM ".$S['TABLE']." WHERE ".$S['ID']."=$id")->fetch();
if (!$sol) { die('Solicitud no encontrada'); }



// Evidencias
$ev = $pdo->query("SELECT ".$E['ID']." AS id, ".$E['NOM']." AS nombre FROM ".$E['TABLE']." WHERE ".$E['SOL']."=$id")->fetchAll();

// Docente
$doc = $pdo->query("SELECT ".$D['NOMBRE']." AS nom, ".$D['AP_PAT']." AS ap, ".$D['AP_MAT']." AS am
                    FROM ".$D['TABLE']." WHERE ".$D['ID']."=".(int)$sol['id_doc'])->fetch();
$nombreDoc = trim(($doc['nom']??'').' '.($doc['ap']??'').' '.($doc['am']??''));
?>
<!doctype html>
<html lang="es"><head>
<meta charset="utf-8"><title>Revisión solicitud</title>
<link rel="stylesheet" href="/siged/app/css/styles.css"></head>
<body class="layout">
  <main class="card">
    <h1>Solicitud #<?= (int)$sol['id']?> · <?= htmlspecialchars($sol['tipo'])?></h1>
    <p>Docente: <strong><?= htmlspecialchars($nombreDoc) ?></strong></p>
    <p>Estado actual: <strong><?= htmlspecialchars($sol['estado']) ?></strong></p>
    <?php require __DIR__ . '/ci_mount.php'; ?>
    <h3>Evidencias</h3>
    <ul>
      <?php foreach($ev as $f): ?>
        <li><?= htmlspecialchars($f['nombre']) ?> — <a href="/siged/public/index.php?action=sol_descargar&id=<?= (int)$f['id']?>">Descargar</a></li>
      <?php endforeach; ?>
      <?php if(!$ev): ?><li><em>Sin evidencias</em></li><?php endif; ?>
    </ul>

    <?php if ($sol['estado']==='ENVIADA'): ?>
      <?php
// Mostrar formulario CI sólo si el tipo es ACI
if (($sol['TIPO_DOCUMENTO'] ?? '') === 'ACI') {
  require __DIR__ . '/ci_form.php';
}
?>
<?php
// RED: formulario del Depto (asignatura + programa)
if (($sol['tipo'] ?? '') === 'RED') {
  require __DIR__ . '/dep_form.php';
}
?>
    <h3>Decisión</h3>
    <form method="post" action="/siged/public/index.php?action=jefe_decidir">
      <input type="hidden" name="id" value="<?= (int)$sol['id'] ?>">
      <label>Comentario (opcional)
        <textarea name="comentario" rows="3" style="width:100%"></textarea>
      </label>
      <button name="decision" value="APROBADA">Aprobar (autoriza firma)</button>
      <?php if ($sol['estado']==='APROBADA'): ?>
       <p><a target="_blank" href="/siged/public/index.php?action=sol_pdf&id=<?= (int)$sol['id']?>">Generar / Ver PDF</a></p>
      <?php endif; ?>
      <button name="decision" value="RECHAZADA" style="margin-left:8px;background:#991B1B">Rechazar</button>
    </form>
    <?php endif; ?>

    <p><a href="/siged/public/index.php?action=jefe_bandeja">← Volver</a></p>
  </main>
</body></html>
