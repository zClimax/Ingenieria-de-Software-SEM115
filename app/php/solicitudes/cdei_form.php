<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';

// Montado por cdei_mount.php
if (!isset($sol) || strtoupper($sol['tipo'] ?? '') !== 'CDEI') {
  return;
}

$pdo = DB::conn();
$sid = (int)($sol['id'] ?? 0);
if ($sid <= 0) return;

// Buscar si ya existe registro
$st = $pdo->prepare("
  SELECT ID_EDI_DIPLOMADO, NOMBRE_MODULO, HORAS_IMPARTIDAS
  FROM dbo.DOCENTE_DIPLOMADO_EDUCACION_INCLUSIVA
  WHERE ID_SOLICITUD = :sid
");
$st->execute([':sid' => $sid]);
$E = $st->fetch(PDO::FETCH_ASSOC);

$idReg   = (int)($E['ID_EDI_DIPLOMADO'] ?? 0);
$nomMod  = $E['NOMBRE_MODULO']      ?? '';
$horas   = (int)($E['HORAS_IMPARTIDAS'] ?? 40);
?>
<section class="card" style="margin-top:1rem;padding:1rem 1.25rem">
  <h3 style="margin:0 0 .75rem">Diplomado en Educación Inclusiva (CDEI)</h3>

  <form method="post"
        action="/siged/public/index.php?action=cdei_guardar"
        style="display:grid;gap:.75rem">
    <input type="hidden" name="id" value="<?= $sid ?>">
    <input type="hidden" name="id_edi_diplomado" value="<?= $idReg ?>">

    <label>Nombre del módulo
      <input type="text"
             name="nombre_modulo"
             required
             value="<?= htmlspecialchars($nomMod) ?>"
             placeholder='p.ej. "Estrategias de educación inclusiva en el aula"'>
    </label>

    <div style="max-width:220px">
      <label>Horas impartidas
        <input type="number"
               name="horas_impartidas"
               min="1"
               step="1"
               required
               value="<?= $horas > 0 ? $horas : 40 ?>">
      </label>
    </div>

    <div style="display:flex;gap:.5rem;margin-top:.5rem">
      <button type="submit" class="btn">Guardar datos</button>
      <a class="btn" href="/siged/public/index.php?action=jefe_ver&id=<?= $sid ?>">Volver</a>
    </div>
  </form>

  <?php if (isset($_GET['cdei_saved'])): ?>
    <div class="alert" style="margin-top:.5rem;background:#ecfdf3;border:1px solid #bbf7d0;color:#166534;padding:.5rem;border-radius:.5rem">
      Información del módulo guardada correctamente.
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['cdei_err']) && $_GET['cdei_err']==='campos'): ?>
    <div class="alert" style="margin-top:.5rem;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:.5rem;border-radius:.5rem">
      Por favor completa el nombre del módulo y las horas impartidas.
    </div>
  <?php endif; ?>
</section>
