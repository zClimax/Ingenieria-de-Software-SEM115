<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';
requireRole(['JEFE_DEPARTAMENTO']);

$pdo = DB::conn();
$u   = Session::user();
$idUsuario = (int)($u['id'] ?? $u['ID_USUARIO'] ?? 0);

/* 1) Resuelve el depto del Jefe de forma segura (session -> BD) */
$depJefe = (int)($u['id_departamento'] ?? $u['ID_DEPARTAMENTO'] ?? 0);
if ($depJefe <= 0 && $idUsuario > 0) {
  $qDep = $pdo->prepare("SELECT ID_DEPARTAMENTO FROM dbo.USUARIOS WHERE ID_USUARIO=:u");
  $qDep->execute([':u'=>$idUsuario]);
  $depJefe = (int)($qDep->fetchColumn() ?: 0);
}

/* 2) Solicitud */
$sid = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($sid <= 0) { echo '<div class="alert error">ID inválido</div>'; return; }

$qSol = $pdo->prepare("
  SELECT ID_SOLICITUD, TIPO_DOCUMENTO, ESTADO, ID_DEPARTAMENTO_APROBADOR
  FROM dbo.SOLICITUD_DOCUMENTO
  WHERE ID_SOLICITUD = :id
");
$qSol->execute([':id'=>$sid]);
$S = $qSol->fetch(PDO::FETCH_ASSOC);
if (!$S) { echo '<div class="alert error">Solicitud no encontrada</div>'; return; }
if ($S['TIPO_DOCUMENTO'] !== 'ESTR') { /* no es este tipo, no montes nada */ return; }

/* Debug opcional */
if (!empty($_GET['estr_dbg'])) {
  echo '<div class="alert warn" style="white-space:pre-line">';
  echo "DBG · jefe dep = {$depJefe}\n";
  echo "DBG · sol dep_apr = ".(int)$S['ID_DEPARTAMENTO_APROBADOR']."\n";
  echo "DBG · estado = {$S['ESTADO']}\n";
  echo "</div>";
}

/* 3) Gate: sólo el Jefe del departamento aprobador puede ver/capturar */
$depApr = (int)($S['ID_DEPARTAMENTO_APROBADOR'] ?? 0);
if ($depApr <= 0) { echo '<div class="alert error">La solicitud no tiene departamento aprobador.</div>'; return; }
if ($depJefe !== $depApr) {
  echo '<div class="alert error">No eres el jefe del departamento aprobador.</div>';
  return;
}

/* 4) Carga previa (si existe) */
$qPrev = $pdo->prepare("
  SELECT TOP 1 ASIGNATURA, ESTRATEGIA, PROGRAMA_EDUCATIVO, LUGAR, FECHA_EMISION
  FROM dbo.DOC_DEP_ESTRAT
  WHERE ID_SOLICITUD = :id
  ORDER BY ID_ESTRAT DESC
");
$qPrev->execute([':id'=>$sid]);
$prev = $qPrev->fetch(PDO::FETCH_ASSOC) ?: [
  'ASIGNATURA' => '',
  'ESTRATEGIA' => '',
  'PROGRAMA_EDUCATIVO' => '',
  'LUGAR' => 'Culiacán, Sinaloa',
  'FECHA_EMISION' => date('Y-m-d'),
];

/* 5) Render del mini-CRUD */
?>
<section class="card" style="margin-bottom:12px">
  <h3>Datos para la Constancia (Estrategias Didácticas)</h3>

  <?php if (($S['ESTADO'] ?? '') !== 'ENVIADA'): ?>
    <div class="alert warn">La solicitud está en estado <strong><?=htmlspecialchars($S['ESTADO'])?></strong>. Solo se edita en ENVIADA.</div>
  <?php endif; ?>

  <form method="post" action="/siged/public/index.php?action=estr_guardar">
    <input type="hidden" name="id" value="<?= (int)$sid ?>">

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
      <label>Asignatura
        <input type="text" name="asignatura" value="<?= htmlspecialchars($prev['ASIGNATURA']) ?>" required>
      </label>
      <label>Programa educativo
        <input type="text" name="programa" value="<?= htmlspecialchars($prev['PROGRAMA_EDUCATIVO']) ?>" required>
      </label>
    </div>

    <label>Estrategia (descripción breve)
      <input type="text" name="estrategia" value="<?= htmlspecialchars($prev['ESTRATEGIA']) ?>" required>
    </label>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:10px">
      <label>Lugar
        <input type="text" name="lugar" value="<?= htmlspecialchars($prev['LUGAR']) ?>">
      </label>
      <label>Fecha de emisión
        <input type="date" name="fecha" value="<?= htmlspecialchars($prev['FECHA_EMISION']) ?>">
      </label>
    </div>

    <button type="submit" class="btn primary" <?=($S['ESTADO']==='ENVIADA'?'':'disabled')?>>
      Guardar datos
    </button>
  </form>
</section>
