<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';
requireRole(['DOCENTE']);

$pdo = DB::conn();
$S = Config::MAP['SOLICITUD']; $E = Config::MAP['EVID'];
$ts=$S['TABLE']; $sId=$S['ID']; $sTipo=$S['TIPO']; $sEstado=$S['ESTADO'];

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
  echo '<p style="color:#b91c1c">No se recibió un ID de solicitud. Vuelve a crear la solicitud.</p>';
  exit;
}

$sol = $pdo->query("SELECT $sId AS id, $sTipo AS tipo, $sEstado AS estado FROM $ts WHERE $sId = $id")->fetch();
if (!$sol) { die('Solicitud no encontrada'); }

$te=$E['TABLE']; $eId=$E['ID']; $eNom=$E['NOM']; $eRuta=$E['RUTA'];
$ev = $pdo->query("SELECT $eId AS id, $eNom AS nombre FROM $te WHERE ".$E['SOL']." = $id")->fetchAll();
?>
<!doctype html>
<html lang="es"><head>
<meta charset="utf-8"><title>Editar solicitud</title>
<link rel="stylesheet" href="/siged/app/css/styles.css"></head>
<body class="layout">
  <main class="card">
    <h1>Solicitud #<?= (int)$sol['id'] ?> (<?= htmlspecialchars($sol['tipo']) ?>)</h1>
    <p class="muted">Estado: <strong><?= htmlspecialchars($sol['estado']) ?></strong></p>

    <?php if ($sol['estado']==='BORRADOR'): ?>
    <h3>Subir evidencias</h3>
    <form method="post" enctype="multipart/form-data" action="/siged/public/index.php?action=sol_subir">
      <input type="hidden" name="id" value="<?= (int)$sol['id'] ?>">
      <input type="file" name="archivo" required>
      <p class="muted">Permitidos: PDF/JPG/PNG · Máx 5 MB</p>
      <button type="submit">Subir</button>
    </form>

    <form method="post" action="/siged/public/index.php?action=sol_enviar" style="margin-top:16px">
      <input type="hidden" name="id" value="<?= (int)$sol['id'] ?>">
      <button type="submit">Enviar a validación</button>
    </form>
    <?php endif; ?>

    <h3>Evidencias</h3>
    <ul>
      <?php foreach ($ev as $f): ?>
        <li>
          <?= htmlspecialchars($f['nombre']) ?>
          — <a href="/siged/public/index.php?action=sol_descargar&id=<?= (int)$f['id'] ?>">Descargar</a>
        </li>
      <?php endforeach; ?>
      <?php if (!$ev): ?><li><em>Sin evidencias aún</em></li><?php endif; ?>
    </ul>

    <p><a href="/siged/public/index.php?action=sol_mis">← Mis solicitudes</a></p>
  </main>
</body></html>
