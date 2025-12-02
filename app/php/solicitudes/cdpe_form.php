<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';

// Montado por cdpe_mount.php
if (!isset($sol) || strtoupper($sol['tipo'] ?? '') !== 'CDPE') {
  return;
}

$pdo = DB::conn();
$sid = (int)($sol['id'] ?? 0);
if ($sid <= 0) return;

// Buscar si ya existe registro
$st = $pdo->prepare("
  SELECT ID_DIPLOMADO_ESTRATEGICO, NOMBRE_DIPLOMADO, NOMBRE_PROYECTO
  FROM dbo.DOCENTE_DIPLOMADO_ESTRATEGICO
  WHERE ID_SOLICITUD = :sid
");
$st->execute([':sid' => $sid]);
$D = $st->fetch(PDO::FETCH_ASSOC);

$idReg        = (int)($D['ID_DIPLOMADO_ESTRATEGICO'] ?? 0);
$nombreDip    = $D['NOMBRE_DIPLOMADO'] ?? '';
$nombreProy   = $D['NOMBRE_PROYECTO']  ?? '';
?>
<section class="card" style="margin-top:1rem;padding:1rem 1.25rem">
  <h3 style="margin:0 0 .75rem">Comisión Diplomados Estratégicos (CDPE)</h3>

  <form method="post"
        action="/siged/public/index.php?action=cdpe_guardar"
        style="display:grid;gap:.75rem">
    <input type="hidden" name="id" value="<?= $sid ?>">
    <input type="hidden" name="id_dip_estrategico" value="<?= $idReg ?>">

    <label>Nombre del diplomado
      <input type="text"
             name="nombre_diplomado"
             required
             value="<?= htmlspecialchars($nombreDip) ?>"
             placeholder='p.ej. "Diplomado en Gestión de la Innovación Tecnológica"'>
    </label>

    <label>Nombre del proyecto estratégico del TecNM
      <input type="text"
             name="nombre_proyecto"
             required
             value="<?= htmlspecialchars($nombreProy) ?>"
             placeholder='p.ej. "Proyecto Estratégico de Innovación y Transferencia Tecnológica"'>
    </label>

    <div style="display:flex;gap:.5rem;margin-top:.5rem">
      <button type="submit" class="btn">Guardar datos</button>
      <a class="btn" href="/siged/public/index.php?action=jefe_ver&id=<?= $sid ?>">Volver</a>
    </div>
  </form>

  <?php if (isset($_GET['cdpe_saved'])): ?>
    <div class="alert" style="margin-top:.5rem;background:#ecfdf3;border:1px solid #bbf7d0;color:#166534;padding:.5rem;border-radius:.5rem">
      Información del diplomado estratégico guardada correctamente.
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['cdpe_err']) && $_GET['cdpe_err']==='campos'): ?>
    <div class="alert" style="margin-top:.5rem;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:.5rem;border-radius:.5rem">
      Por favor captura el nombre del diplomado y el proyecto estratégico.
    </div>
  <?php endif; ?>
</section>
