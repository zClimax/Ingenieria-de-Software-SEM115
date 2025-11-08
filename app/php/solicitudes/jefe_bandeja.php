<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';
requireRole(['JEFE_DEPARTAMENTO']);

$pdo = DB::conn();

$S = Config::MAP['SOLICITUD'];   // mapeo de SOLICITUD_DOCUMENTO
$D = Config::MAP['DOCENTE'];     // mapeo de DOCENTE
$U = Config::MAP['USUARIOS'];    // mapeo de USUARIOS

$ts = $S['TABLE'];  // dbo.SOLICITUD_DOCUMENTO
$td = $D['TABLE'];  // dbo.DOCENTE
$tu = $U['TABLE'];  // dbo.USUARIOS

$user = Session::user();
$idUsuario = (int)$user['id'];

/* ✅ Obtener SIEMPRE el dep del jefe desde BD (evita caché de sesión) */
$sqlDepJefe = "SELECT {$U['DEP']} AS dep FROM $tu WHERE {$U['ID']} = :uid";
$stmt = $pdo->prepare($sqlDepJefe);
$stmt->execute([':uid' => $idUsuario]);
$rowDep = $stmt->fetch();
$idDepJefe = (int)($rowDep['dep'] ?? 0);

/* Modo diagnóstico: ver todas las ENVIADAS sin filtrar por depto */
$showAll = isset($_GET['all']) && $_GET['all']=='1';

if ($showAll) {
  $sql = "
    SELECT 
      S.{$S['ID']}       AS id,
      S.{$S['TIPO']}     AS tipo,
      S.{$S['F_ENV']}    AS enviada,
      S.{$S['DEP_APROB']} AS dep_aprob,
      D.{$D['NOMBRE']}   AS nombre,
      D.{$D['AP_PAT']}   AS ap,
      D.{$D['AP_MAT']}   AS am
    FROM $ts S
    JOIN $td D ON D.{$D['ID']} = S.{$S['DOC']}
    WHERE RTRIM(LTRIM(S.{$S['ESTADO']})) = 'ENVIADA'
    ORDER BY S.{$S['ID']} DESC";
  $stmt = $pdo->query($sql);
} else {
  $sql = "
    SELECT 
      S.{$S['ID']}       AS id,
      S.{$S['TIPO']}     AS tipo,
      S.{$S['F_ENV']}    AS enviada,
      D.{$D['NOMBRE']}   AS nombre,
      D.{$D['AP_PAT']}   AS ap,
      D.{$D['AP_MAT']}   AS am
    FROM $ts S
    JOIN $td D ON D.{$D['ID']} = S.{$S['DOC']}
    WHERE RTRIM(LTRIM(S.{$S['ESTADO']})) = 'ENVIADA'
      AND S.{$S['DEP_APROB']} = :depAprob
    ORDER BY S.{$S['ID']} DESC";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':depAprob' => $idDepJefe]);
}
$rows = $stmt->fetchAll();
?>
<!doctype html>
<html lang="es"><head>
<meta charset="utf-8"><title>Bandeja del Jefe</title>
<link rel="stylesheet" href="/siged/app/css/styles.css"></head>
<body class="layout">
  <main class="card">
    <h1>Bandeja de validación</h1>
    <p class="muted">Tu departamento aprobador (desde BD): <strong><?= (int)$idDepJefe ?></strong>
      · <a href="/siged/public/index.php?action=jefe_bandeja&all=1">ver todas (diagnóstico)</a></p>
    <ul>
    <?php foreach($rows as $r): ?>
      <li>
        #<?= (int)$r['id']?> · <?= htmlspecialchars($r['tipo'])?>
        <?php if ($showAll && isset($r['dep_aprob'])): ?> · dep_aprob: <?= (int)$r['dep_aprob']?><?php endif; ?>
        · Docente: <?= htmlspecialchars(trim(($r['nombre']??'').' '.($r['ap']??'').' '.($r['am']??''))) ?>
        — <a href="/siged/public/index.php?action=jefe_ver&id=<?= (int)$r['id']?>">Revisar</a>
      </li>
    <?php endforeach; ?>
    <?php if(!$rows): ?><li><em>Sin solicitudes ENVIADAS para tu filtro.</em></li><?php endif; ?>
    </ul>
  </main>
</body></html>
