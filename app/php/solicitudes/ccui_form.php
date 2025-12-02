<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';

// Montado por ccui_mount.php
if (!isset($sol) || strtoupper($sol['tipo'] ?? '') !== 'CCUI') {
  return;
}

$pdo = DB::conn();
$sid = (int)($sol['id'] ?? 0);
if ($sid <= 0) return;

// Buscar si ya existe registro
$st = $pdo->prepare("
  SELECT ID_CURSO_IMPARTIDO, NOMBRE_CURSO, NUMERO_REGISTRO,
         FECHA_INICIO, FECHA_FIN, NUMERO_HORAS
  FROM dbo.DOCENTE_CURSO_IMPARTIDO
  WHERE ID_SOLICITUD = :sid
");
$st->execute([':sid' => $sid]);
$C = $st->fetch(PDO::FETCH_ASSOC);

$idCurso   = (int)($C['ID_CURSO_IMPARTIDO'] ?? 0);
$nomCurso  = $C['NOMBRE_CURSO']      ?? '';
$numReg    = $C['NUMERO_REGISTRO']   ?? '';
$fIni      = $C['FECHA_INICIO']      ?? '';
$fFin      = $C['FECHA_FIN']         ?? '';
$horas     = (int)($C['NUMERO_HORAS'] ?? 30);
?>
<section class="card" style="margin-top:1rem;padding:1rem 1.25rem">
  <h3 style="margin:0 0 .75rem">Datos del curso impartido (CCUI)</h3>

  <form method="post"
        action="/siged/public/index.php?action=ccui_guardar"
        style="display:grid;gap:.75rem">
    <input type="hidden" name="id" value="<?= $sid ?>">
    <input type="hidden" name="id_curso_impartido" value="<?= $idCurso ?>">

    <label>Nombre del curso
      <input type="text"
             name="nombre_curso"
             required
             value="<?= htmlspecialchars($nomCurso) ?>"
             placeholder='p.ej. "Estrategias de enseñanza centradas en el estudiante"'>
    </label>

    <label>Número de registro
      <input type="text"
             name="numero_registro"
             required
             value="<?= htmlspecialchars($numReg) ?>"
             placeholder="p.ej. TECNM-FO-DC-123/2024">
    </label>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
      <label>Fecha de inicio
        <input type="date"
               name="fecha_inicio"
               required
               value="<?= htmlspecialchars($fIni) ?>">
      </label>
      <label>Fecha de término
        <input type="date"
               name="fecha_fin"
               required
               value="<?= htmlspecialchars($fFin) ?>">
      </label>
    </div>

    <div style="max-width:220px">
      <label>Duración (horas)
        <input type="number"
               name="numero_horas"
               min="30"
               step="1"
               required
               value="<?= $horas > 0 ? $horas : 30 ?>">
      </label>
    </div>

    <div style="display:flex;gap:.5rem;margin-top:.5rem">
      <button type="submit" class="btn">Guardar datos del curso</button>
      <a class="btn" href="/siged/public/index.php?action=jefe_ver&id=<?= $sid ?>">Volver</a>
    </div>
  </form>

  <?php if (isset($_GET['ccui_saved'])): ?>
    <div class="alert" style="margin-top:.5rem;background:#ecfdf3;border:1px solid #bbf7d0;color:#166534;padding:.5rem;border-radius:.5rem">
      Información guardada correctamente.
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['ccui_err']) && $_GET['ccui_err']==='campos'): ?>
    <div class="alert" style="margin-top:.5rem;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:.5rem;border-radius:.5rem">
      Por favor completa todos los campos obligatorios del curso impartido.
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['ccui_err']) && $_GET['ccui_err']==='horas'): ?>
    <div class="alert" style="margin-top:.5rem;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:.5rem;border-radius:.5rem">
      La duración del curso debe ser de al menos 30 horas, conforme al criterio 1.2.2.2.
    </div>
  <?php endif; ?>
</section>
