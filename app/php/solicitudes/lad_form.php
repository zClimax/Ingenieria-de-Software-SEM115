<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/session.php';

if (!isset($sol) || ($sol['tipo'] ?? '') !== 'LAD') {
    return;
}

$pdo = DB::conn();
$sid = (int)$sol['id'];

// Cargar registro existente (si lo hay)
$st = $pdo->prepare("SELECT TOP 1 * FROM dbo.DOCENTE_LAD WHERE ID_SOLICITUD=:sid ORDER BY ID_LAD DESC");
$st->execute([':sid'=>$sid]);
$rowLAD = $st->fetch(PDO::FETCH_ASSOC);

$valAct = function(string $campo) use ($rowLAD): string {
    $v = strtoupper((string)($rowLAD[$campo] ?? 'NA'));
    return in_array($v, ['SI','NO','NA'], true) ? $v : 'NA';
};

$semestre = htmlspecialchars((string)($rowLAD['SEMESTRE'] ?? ''));
$liberado = isset($rowLAD['LIBERADO']) ? (int)$rowLAD['LIBERADO'] : 1;
$notas    = htmlspecialchars((string)($rowLAD['NOTAS'] ?? ''));
?>
<section class="card" style="margin-top:1rem;padding:1rem 1.25rem">
  <h3 style="margin:0 0 .75rem">Datos para la Constancia de Liberación de Actividades</h3>

  <form method="post"
        action="/siged/public/index.php?action=lad_guardar"
        style="display:grid;gap:1rem">
    <input type="hidden" name="id" value="<?= $sid ?>">

    <div style="display:grid;grid-template-columns:1fr;gap:.75rem">
      <label>Semestre / periodo evaluado
        <input type="text"
               name="semestre"
               required
               placeholder="Ej. Enero–Junio 2025 o Agosto–Diciembre 2025"
               value="<?= $semestre ?>">
      </label>
    </div>

    <div>
      <p style="margin:.5rem 0;font-weight:600">Evaluación de actividades docentes</p>
      <table style="width:100%;border-collapse:collapse;font-size:.9rem">
        <thead>
          <tr>
            <th style="border:1px solid #ddd;padding:.35rem;width:5%">No.</th>
            <th style="border:1px solid #ddd;padding:.35rem">Actividad</th>
            <th style="border:1px solid #ddd;padding:.35rem;width:8%">Sí</th>
            <th style="border:1px solid #ddd;padding:.35rem;width:8%">No</th>
            <th style="border:1px solid #ddd;padding:.35rem;width:8%">N/A</th>
          </tr>
        </thead>
        <tbody>
          <?php
          $acts = [
            1 => 'La elaboración y entrega de la dosificación de la planeación del curso y avance programático de las materias impartidas.',
            2 => 'La elaboración y entrega de la instrumentación didáctica.',
            3 => 'El 100% del contenido de los programas de estudio.',
            4 => 'La entrega en tiempo y forma de calificaciones parciales y finales.',
            5 => 'La entrega en tiempo y forma del reporte final.',
            6 => 'La entrega del informe de los proyectos individuales / horas de apoyo a la docencia del programa de trabajo académico realizados en horas de apoyo a la docencia.',
            7 => 'Entrega de índices de reprobación y deserción mensuales y finales.',
          ];

          foreach ($acts as $num => $texto):
              $campo = 'ACT'.$num;
              $cur   = $valAct($campo);
          ?>
          <tr>
            <td style="border:1px solid #ddd;padding:.35rem;text-align:center"><?= $num ?></td>
            <td style="border:1px solid #ddd;padding:.35rem"><?= htmlspecialchars($texto) ?></td>
            <td style="border:1px solid #ddd;padding:.35rem;text-align:center">
              <input type="radio" name="act<?= $num ?>" value="SI" <?= $cur==='SI'?'checked':'' ?>>
            </td>
            <td style="border:1px solid #ddd;padding:.35rem;text-align:center">
              <input type="radio" name="act<?= $num ?>" value="NO" <?= $cur==='NO'?'checked':'' ?>>
            </td>
            <td style="border:1px solid #ddd;padding:.35rem;text-align:center">
              <input type="radio" name="act<?= $num ?>" value="NA" <?= $cur==='NA'?'checked':'' ?>>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <small style="color:#6b7280;display:block;margin-top:.35rem">
        Nota: El punto 6 no aplica para docentes por horas (usar N/A).
      </small>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;align-items:flex-start">
      <div>
        <label style="font-weight:600;display:block;margin-bottom:.35rem">Resultado global</label>
        <label style="display:block;margin-bottom:.25rem">
          <input type="radio" name="liberado" value="1" <?= $liberado ? 'checked' : '' ?>>
          Se otorga la liberación de actividades
        </label>
        <label style="display:block;">
          <input type="radio" name="liberado" value="0" <?= $liberado ? '' : 'checked' ?>>
          No se otorga la liberación de actividades
        </label>
      </div>
      <div>
        <label>Observaciones (opcional)
          <textarea name="notas" rows="3" style="width:100%;resize:vertical;"><?= $notas ?></textarea>
        </label>
      </div>
    </div>

    <div style="display:flex;gap:.5rem;margin-top:.5rem">
      <button type="submit" class="btn">Guardar datos</button>
      <a href="/siged/public/index.php?action=jefe_ver&id=<?= $sid ?>" class="btn">Cancelar</a>
    </div>

    <?php if (!empty($_GET['lad_saved'])): ?>
      <div class="alert" style="margin-top:.5rem">Datos de liberación guardados correctamente.</div>
    <?php endif; ?>
  </form>
</section>
