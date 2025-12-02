<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';

// Montado por cmp_mount.php
if (!isset($sol) || strtoupper($sol['tipo'] ?? '') !== 'CMP') {
  return;
}

$pdo = DB::conn();
$sid = (int)($sol['id'] ?? 0);
if ($sid <= 0) return;

// Buscar si ya existe registro del manual
$st = $pdo->prepare("
  SELECT ID_MANUAL, NOMBRE_MANUAL
  FROM dbo.DOCENTE_MANUAL_PRACTICAS
  WHERE ID_SOLICITUD = :sid
");
$st->execute([':sid' => $sid]);
$M = $st->fetch(PDO::FETCH_ASSOC);

$idManual     = (int)($M['ID_MANUAL'] ?? 0);
$nombreManual = $M['NOMBRE_MANUAL'] ?? '';
?>
<section class="card" style="margin-top:1rem;padding:1rem 1.25rem">
  <h3 style="margin:0 0 .75rem">Manual de prácticas (CMP)</h3>

  <form method="post" action="/siged/public/index.php?action=cmp_guardar" style="display:grid;gap:.75rem">
    <input type="hidden" name="id" value="<?= $sid ?>">
    <input type="hidden" name="id_manual" value="<?= $idManual ?>">

    <label>Nombre del manual de prácticas
      <input type="text"
             name="nombre_manual"
             required
             value="<?= htmlspecialchars($nombreManual) ?>"
             placeholder='p.ej. "Manual de prácticas de laboratorio de química"'>
    </label>

    <div style="display:flex;gap:.5rem;margin-top:.5rem">
      <button type="submit" class="btn">Guardar manual</button>
      <a class="btn" href="/siged/public/index.php?action=jefe_ver&id=<?= $sid ?>">Volver</a>
    </div>
  </form>

  <?php if (isset($_GET['cmp_saved'])): ?>
    <div class="alert" style="margin-top:.5rem;background:#ecfdf3;border:1px solid #bbf7d0;color:#166534;padding:.5rem;border-radius:.5rem">
      Manual de prácticas guardado correctamente.
    </div>
  <?php endif; ?>
</section>
