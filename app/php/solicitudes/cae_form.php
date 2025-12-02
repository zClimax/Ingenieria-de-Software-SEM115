<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';

// Montado por cae_mount.php
if (!isset($sol) || strtoupper($sol['tipo'] ?? '') !== 'CAE') {
  return;
}

$pdo = DB::conn();
$sid = (int)($sol['id'] ?? 0);
if ($sid <= 0) return;

// Buscar si ya existe registro
$st = $pdo->prepare("
  SELECT ID_ACTA_EXAMEN,
         TIPO_EXAMEN,
         FECHA_EXAMEN,
         NOMBRE_ESTUDIANTE,
         PROGRAMA,
         ROL_PARTICIPACION
  FROM dbo.DOCENTE_ACTA_EXAMEN
  WHERE ID_SOLICITUD = :sid
");
$st->execute([':sid' => $sid]);
$A = $st->fetch(PDO::FETCH_ASSOC);

$idActa          = (int)($A['ID_ACTA_EXAMEN'] ?? 0);
$tipoExamen      = $A['TIPO_EXAMEN']       ?? '';
$fechaExamen     = $A['FECHA_EXAMEN']      ?? '';
$nombreEstudiante= $A['NOMBRE_ESTUDIANTE'] ?? '';
$programa        = $A['PROGRAMA']          ?? '';
$rolPart         = $A['ROL_PARTICIPACION'] ?? '';
?>
<section class="card" style="margin-top:1rem;padding:1rem 1.25rem">
  <h3 style="margin:0 0 .75rem">Acta de examen profesional / de grado (CAE)</h3>

  <form method="post"
        action="/siged/public/index.php?action=cae_guardar"
        style="display:grid;gap:.75rem">
    <input type="hidden" name="id" value="<?= $sid ?>">
    <input type="hidden" name="id_acta_examen" value="<?= $idActa ?>">

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
      <label>Tipo de examen
        <select name="tipo_examen" required>
          <option value="">Seleccione...</option>
          <option value="profesional" <?= $tipoExamen==='profesional' ? 'selected' : '' ?>>Profesional</option>
          <option value="de grado" <?= $tipoExamen==='de grado' ? 'selected' : '' ?>>De grado (posgrado)</option>
        </select>
      </label>

      <label>Fecha del examen
        <input type="text"
               name="fecha_examen"
               required
               value="<?= htmlspecialchars($fechaExamen) ?>"
               placeholder="p.ej. 15 de julio de 2024">
      </label>
    </div>

    <label>Nombre del estudiante
      <input type="text"
             name="nombre_estudiante"
             required
             value="<?= htmlspecialchars($nombreEstudiante) ?>"
             placeholder="Nombre completo del estudiante">
    </label>

    <label>Programa
      <input type="text"
             name="programa"
             required
             value="<?= htmlspecialchars($programa) ?>"
             placeholder="p.ej. Maestría en Ingeniería Industrial">
    </label>

    <label>Rol en el acta
      <select name="rol_participacion" required>
        <option value="">Seleccione...</option>
        <option value="Presidente(a) del jurado" <?= $rolPart==='Presidente(a) del jurado' ? 'selected' : '' ?>>Presidente(a) del jurado</option>
        <option value="Director(a)" <?= $rolPart==='Director(a)' ? 'selected' : '' ?>>Director(a)</option>
        <option value="Codirector(a)" <?= $rolPart==='Codirector(a)' ? 'selected' : '' ?>>Codirector(a)</option>
        <option value="Asesor(a)" <?= $rolPart==='Asesor(a)' ? 'selected' : '' ?>>Asesor(a)</option>
      </select>
    </label>

    <div style="display:flex;gap:.5rem;margin-top:.5rem">
      <button type="submit" class="btn">Guardar datos</button>
      <a class="btn" href="/siged/public/index.php?action=jefe_ver&id=<?= $sid ?>">Volver</a>
    </div>
  </form>

  <?php if (isset($_GET['cae_saved'])): ?>
    <div class="alert" style="margin-top:.5rem;background:#ecfdf3;border:1px solid #bbf7d0;color:#166534;padding:.5rem;border-radius:.5rem">
      Información del acta de examen guardada correctamente.
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['cae_err']) && $_GET['cae_err']==='campos'): ?>
    <div class="alert" style="margin-top:.5rem;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:.5rem;border-radius:.5rem">
      Por favor completa todos los campos requeridos del acta.
    </div>
  <?php endif; ?>
</section>
