<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';
requireRole(['DOCENTE']);

$pdo = DB::conn();
$S = Config::MAP['SOLICITUD']; $D = Config::MAP['DOCENTE'];
$ts=$S['TABLE']; $sId=$S['ID']; $sTipo=$S['TIPO']; $sEstado=$S['ESTADO']; $sF=$S['F_CRE'];

$user = Session::user();
$doc = $pdo->prepare("SELECT ".$D['ID']." AS id_doc FROM ".$D['TABLE']." WHERE ".$D['ID_USR']."=:u");
$doc->execute([':u'=>$user['id']]);
$docRow = $doc->fetch();
if (!$docRow) { die('No se encontró DOCENTE vinculado'); }
$idDoc = (int)$docRow['id_doc'];

$rows = $pdo->query("SELECT $sId AS id, $sTipo AS tipo, $sEstado AS estado, $sF AS creada
                     FROM $ts WHERE ".$S['DOC']."=$idDoc ORDER BY $sId DESC")->fetchAll();
?>
<!doctype html>
<html lang="es"><head>
<meta charset="utf-8"><title>Mis solicitudes</title>
<link rel="stylesheet" href="/siged/app/css/styles.css"></head>
<body class="layout">
  <main class="card">
    <h1>Mis solicitudes</h1>
    <p><a href="/siged/public/index.php?action=sol_nueva">+ Nueva solicitud</a></p>
    <ul>
      <?php foreach($rows as $r): ?>
        <li>
          #<?= (int)$r['id'] ?> · <?= htmlspecialchars($r['tipo']) ?> · <?= htmlspecialchars($r['estado']) ?> · <?= htmlspecialchars($r['creada']) ?>
          <?php if ($r['estado']==='BORRADOR'): ?>
            — <a href="/siged/public/index.php?action=sol_editar&id=<?= (int)$r['id'] ?>">Editar</a>
            <?php elseif ($r['estado']==='APROBADA'): ?>
            — <a target="_blank" href="/siged/public/index.php?action=sol_pdf&id=<?= (int)$r['id'] ?>">Generar / Ver PDF</a>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
      <?php if (!$rows): ?><li><em>Sin solicitudes</em></li><?php endif; ?>
    </ul>
    <p><a href="/siged/public/index.php">← Inicio</a></p>
  </main>
</body></html>
