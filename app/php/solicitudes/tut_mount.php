<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';
requireRole(['JEFE_DEPARTAMENTO']);

$pdo     = DB::conn();
$u       = Session::user();
$idUsr   = (int)($u['id'] ?? $u['ID_USUARIO'] ?? 0);
$depJefe = (int)($u['id_departamento'] ?? $u['ID_DEPARTAMENTO'] ?? 0);
if ($depJefe <= 0 && $idUsr > 0) {
  $q = $pdo->prepare("SELECT ID_DEPARTAMENTO FROM dbo.USUARIOS WHERE ID_USUARIO=:u");
  $q->execute([':u'=>$idUsr]);
  $depJefe = (int)($q->fetchColumn() ?: 0);
}

$sid = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($sid <= 0) { echo '<div class="alert error">ID inválido</div>'; return; }

$qSol = $pdo->prepare("
  SELECT ID_SOLICITUD, TIPO_DOCUMENTO, ESTADO, ID_DEPARTAMENTO_APROBADOR
  FROM dbo.SOLICITUD_DOCUMENTO
  WHERE ID_SOLICITUD=:id
");
$qSol->execute([':id'=>$sid]);
$S = $qSol->fetch(PDO::FETCH_ASSOC);
if (!$S || $S['TIPO_DOCUMENTO']!=='TUT') return;

if ((int)$S['ID_DEPARTAMENTO_APROBADOR'] !== $depJefe) {
  echo '<div class="alert error">No eres el jefe del departamento aprobador.</div>'; return;
}

$r = $pdo->prepare("
  SELECT TOP 1 TUT_EJ_2024, TUT_AD_2024, LUGAR, FECHA_EMISION
  FROM dbo.DOC_SE_TUTORADOS
  WHERE ID_SOLICITUD=:id
  ORDER BY ID_TUT DESC
");
$r->execute([':id'=>$sid]);
$prev = $r->fetch(PDO::FETCH_ASSOC) ?: [
  'TUT_EJ_2024'=>0, 'TUT_AD_2024'=>0,
  'LUGAR'=>'Culiacán, Sinaloa', 'FECHA_EMISION'=>date('Y-m-d')
];
?>
<section class="card" style="margin-bottom:12px">
  <h3>Datos para la Constancia (Tutorados)</h3>
  <form method="post" action="/siged/public/index.php?action=tut_guardar">
    <input type="hidden" name="id" value="<?= (int)$sid ?>">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
      <label>Tutorados Ene–Jun 2024
        <input type="number" name="tut_ej" min="0" step="1" value="<?= (int)$prev['TUT_EJ_2024'] ?>" required>
      </label>
      <label>Tutorados Ago–Dic 2024
        <input type="number" name="tut_ad" min="0" step="1" value="<?= (int)$prev['TUT_AD_2024'] ?>" required>
      </label>
    </div>
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:10px">
      <label>Lugar
        <input type="text" name="lugar" value="<?= htmlspecialchars($prev['LUGAR']) ?>">
      </label>
      <label>Fecha de emisión
        <input type="date" name="fecha" value="<?= htmlspecialchars($prev['FECHA_EMISION']) ?>">
      </label>
    </div>
    <p style="font-size:12px;color:#555;margin:.5rem 0 0">
      * Puntaje: 3 pts por alumno, tope 45. El sistema lo calculará automáticamente.
    </p>
    <button type="submit" class="btn primary" <?=($S['ESTADO']==='ENVIADA'?'':'disabled')?>>Guardar datos</button>
  </form>
</section>
