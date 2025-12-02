<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';

// Montado por ccid_mount.php
if (!isset($sol) || strtoupper($sol['tipo'] ?? '') !== 'CCID') {
  return;
}

$pdo = DB::conn();
$sid = (int)($sol['id'] ?? 0);
if ($sid <= 0) return;

// Buscar si ya existe registro
$st = $pdo->prepare("
  SELECT ID_COMISION, NOMBRE_CURSO, TIPO_CURSO, NUMERO_HORAS, NUMERO_OFICIO
  FROM dbo.DOCENTE_COMISION_INSTRUCTOR
  WHERE ID_SOLICITUD = :sid
");
$st->execute([':sid' => $sid]);
$C = $st->fetch(PDO::FETCH_ASSOC);

$idCom    = (int)($C['ID_COMISION'] ?? 0);
$nomCurso = $C['NOMBRE_CURSO']   ?? '';
$tipo     = $C['TIPO_CURSO']     ?? '';
$horas    = (int)($C['NUMERO_HORAS'] ?? 30);
$oficio   = $C['NUMERO_OFICIO']  ?? '';
?>
<section class="card" style="margin-top:1rem;padding:1rem 1.25rem">
  <h3 style="margin:0 0 .75rem">Comisión como instructor(a) de curso (CCID)</h3>

  <form method="post" action="/siged/public/index.php?action=ccid_guardar" style="display:grid;gap:.75rem">
    <input type="hidden" name="id" value="<?= $sid ?>">
    <input type="hidden" name="id_comision" value="<?= $idCom ?>">

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:.75rem">
      <label>Nombre del curso
        <input type="text"
               name="nombre_curso"
               required
               value="<?= htmlspecialchars($nomCurso) ?>"
               placeholder='p.ej. "Evaluación por competencias en el aula"'>
      </label>
      <label>Duración (horas)
        <input type="number"
               name="numero_horas"
               min="30"
               step="1"
               required
               value="<?= $horas > 0 ? $horas : 30 ?>">
      </label>
    </div>

    <label>Tipo de curso
      <select name="tipo_curso" required>
        <option value="">Seleccione una opción</option>
        <option value="formación docente" <?= $tipo==='formación docente'?'selected':'' ?>>Formación docente</option>
        <option value="actualización profesional" <?= $tipo==='actualización profesional'?'selected':'' ?>>Actualización profesional</option>
      </select>
    </label>

    <label>Número de oficio
      <input type="text"
             name="numero_oficio"
             required
             value="<?= htmlspecialchars($oficio) ?>"
             placeholder="p.ej. DAC-123/2024">
    </label>

    <div style="display:flex;gap:.5rem;margin-top:.5rem">
      <button type="submit" class="btn">Guardar datos de la comisión</button>
      <a class="btn" href="/siged/public/index.php?action=jefe_ver&id=<?= $sid ?>">Volver</a>
    </div>
  </form>

  <?php if (isset($_GET['ccid_saved'])): ?>
    <div class="alert" style="margin-top:.5rem;background:#ecfdf3;border:1px solid #bbf7d0;color:#166534;padding:.5rem;border-radius:.5rem">
      Información guardada correctamente.
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['ccid_err']) && $_GET['ccid_err']==='horas'): ?>
    <div class="alert" style="margin-top:.5rem;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:.5rem;border-radius:.5rem">
      La duración del curso debe ser de al menos 30 horas, conforme al criterio 1.2.2.1.
    </div>
  <?php endif; ?>
</section>
