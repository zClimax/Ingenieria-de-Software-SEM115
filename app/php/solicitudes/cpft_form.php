<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';

// Montado por cpft_mount.php
if (!isset($sol) || strtoupper($sol['tipo'] ?? '') !== 'CPFT') {
  return;
}

$pdo = DB::conn();
$sid = (int)($sol['id'] ?? 0);
if ($sid <= 0) return;

// Buscar si ya existe registro para esta solicitud
$st = $pdo->prepare("
  SELECT ID_TUTOR_DIPLOMADO, NOMBRE_MODULO, HORAS_IMPARTIDAS
  FROM dbo.DOCENTE_DIPLOMADO_TUTORES
  WHERE ID_SOLICITUD = :sid
");
$st->execute([':sid' => $sid]);
$T = $st->fetch(PDO::FETCH_ASSOC);

$idReg   = (int)($T['ID_TUTOR_DIPLOMADO'] ?? 0);
$nomMod  = $T['NOMBRE_MODULO']      ?? '';
$horas   = (int)($T['HORAS_IMPARTIDAS'] ?? 20);
?>
<section class="card" style="margin-top:1rem;padding:1rem 1.25rem">
  <h3 style="margin:0 0 .75rem">Diplomado Formación de Tutores (CPFT)</h3>

  <form method="post"
        action="/siged/public/index.php?action=cpft_guardar"
        style="display:grid;gap:.75rem">
    <input type="hidden" name="id" value="<?= $sid ?>">
    <input type="hidden" name="id_tutor_diplomado" value="<?= $idReg ?>">

    <label>Nombre del módulo
      <input type="text"
             name="nombre_modulo"
             required
             value="<?= htmlspecialchars($nomMod) ?>"
             placeholder='p.ej. "Estrategias de tutoría y acompañamiento"'>
    </label>

    <div style="max-width:220px">
      <label>Horas impartidas
        <input type="number"
               name="horas_impartidas"
               min="1"
               step="1"
               required
               value="<?= $horas > 0 ? $horas : 20 ?>">
      </label>
    </div>

    <div style="display:flex;gap:.5rem;margin-top:.5rem">
      <button type="submit" class="btn">Guardar datos</button>
      <a class="btn" href="/siged/public/index.php?action=jefe_ver&id=<?= $sid ?>">Volver</a>
    </div>
  </form>

  <?php if (isset($_GET['cpft_saved'])): ?>
    <div class="alert" style="margin-top:.5rem;background:#ecfdf3;border:1px solid #bbf7d0;color:#166534;padding:.5rem;border-radius:.5rem">
      Información del módulo guardada correctamente.
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['cpft_err']) && $_GET['cpft_err']==='campos'): ?>
    <div class="alert" style="margin-top:.5rem;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:.5rem;border-radius:.5rem">
      Por favor completa el nombre del módulo y las horas impartidas.
    </div>
  <?php endif; ?>
</section>
