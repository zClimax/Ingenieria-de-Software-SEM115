<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';

// Montado por cipc_mount.php
if (!isset($sol) || strtoupper($sol['tipo'] ?? '') !== 'CIPC') {
  return;
}

$pdo = DB::conn();
$sid = (int)($sol['id'] ?? 0);
if ($sid <= 0) return;

// Buscar si ya existe registro del módulo
$st = $pdo->prepare("
  SELECT ID_MODULO_DIPLOMADO, NOMBRE_MODULO, HORAS_IMPARTIDAS
  FROM dbo.DOCENTE_DIPLOMADO_PC
  WHERE ID_SOLICITUD = :sid
");
$st->execute([':sid' => $sid]);
$M = $st->fetch(PDO::FETCH_ASSOC);

$idModulo  = (int)($M['ID_MODULO_DIPLOMADO'] ?? 0);
$nomModulo = $M['NOMBRE_MODULO']      ?? '';
$horas     = (int)($M['HORAS_IMPARTIDAS'] ?? 40);
?>
<section class="card" style="margin-top:1rem;padding:1rem 1.25rem">
  <h3 style="margin:0 0 .75rem">Módulo del Diplomado en Pensamiento Crítico (CIPC)</h3>

  <form method="post"
        action="/siged/public/index.php?action=cipc_guardar"
        style="display:grid;gap:.75rem">
    <input type="hidden" name="id" value="<?= $sid ?>">
    <input type="hidden" name="id_modulo_diplomado" value="<?= $idModulo ?>">

    <label>Nombre del módulo
      <input type="text"
             name="nombre_modulo"
             required
             value="<?= htmlspecialchars($nomModulo) ?>"
             placeholder='p.ej. "Pensamiento crítico y resolución de problemas"'>
    </label>

    <div style="max-width:220px">
      <label>Horas impartidas
        <input type="number"
               name="horas_impartidas"
               min="40"
               step="1"
               required
               value="<?= $horas > 0 ? $horas : 40 ?>">
      </label>
    </div>

    <div style="display:flex;gap:.5rem;margin-top:.5rem">
      <button type="submit" class="btn">Guardar módulo</button>
      <a class="btn" href="/siged/public/index.php?action=jefe_ver&id=<?= $sid ?>">Volver</a>
    </div>
  </form>

  <?php if (isset($_GET['cipc_saved'])): ?>
    <div class="alert" style="margin-top:.5rem;background:#ecfdf3;border:1px solid #bbf7d0;color:#166534;padding:.5rem;border-radius:.5rem">
      Información del módulo guardada correctamente.
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['cipc_err']) && $_GET['cipc_err']==='campos'): ?>
    <div class="alert" style="margin-top:.5rem;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:.5rem;border-radius:.5rem">
      Por favor completa el nombre del módulo y las horas impartidas.
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['cipc_err']) && $_GET['cipc_err']==='horas'): ?>
    <div class="alert" style="margin-top:.5rem;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:.5rem;border-radius:.5rem">
      Las horas impartidas deben ser de al menos 40, conforme al criterio 1.2.2.3 del EDD.
    </div>
  <?php endif; ?>
</section>
