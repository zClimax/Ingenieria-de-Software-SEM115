<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';

// Montado por cst_mount.php
if (!isset($sol) || strtoupper($sol['tipo'] ?? '') !== 'CST') {
  return;
}

$pdo = DB::conn();
$sid = (int)($sol['id'] ?? 0);
if ($sid <= 0) return;

// Sinodalías ligadas a ESTA solicitud
$st = $pdo->prepare("
  SELECT ID_SINODALIA,
         TIPO_EXAMEN,
         NOMBRE_ESTUDIANTE,
         PROGRAMA_EDUCATIVO,
         FOLIO_ACTA,
         FECHA_EXAMEN,
         ROL_PARTICIPACION,
         ORDEN
  FROM dbo.DOCENTE_SINODALIA_TITULACION
  WHERE ID_SOLICITUD = :sid
  ORDER BY ORDEN, ID_SINODALIA
");
$st->execute([':sid' => $sid]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<section class="card" style="margin-top:1rem;padding:1rem 1.25rem">
  <h3 style="margin:0 0 .75rem">Sinodalías de titulación (CST)</h3>

  <form method="post"
        action="/siged/public/index.php?action=cst_guardar"
        style="display:grid;gap:.75rem">
    <input type="hidden" name="id" value="<?= $sid ?>">

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
      <label>Tipo de examen
        <select name="tipo_examen" required>
          <option value="">Seleccione...</option>
          <option value="profesional">Profesional</option>
          <option value="grado">De grado</option>
        </select>
      </label>

      <label>Fecha del examen
        <input type="text"
               name="fecha_examen"
               required
               placeholder="p.ej. 15 de julio de 2024">
      </label>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
      <label>Nombre del estudiante
        <input type="text"
               name="nombre_estudiante"
               required
               placeholder="Nombre completo del estudiante">
      </label>

      <label>Programa educativo
        <input type="text"
               name="programa_educativo"
               required
               placeholder="p.ej. Ingeniería en Sistemas Computacionales">
      </label>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
      <label>Folio del acta
        <input type="text"
               name="folio_acta"
               required
               placeholder="p.ej. ACT-2024-015">
      </label>

      <label>Rol en el examen
        <select name="rol_participacion" required>
          <option value="">Seleccione...</option>
          <option value="Secretario(a)">Secretario(a)</option>
          <option value="Vocal">Vocal</option>
        </select>
      </label>
    </div>

    <div style="display:flex;gap:.5rem;margin-top:.5rem">
      <button type="submit" class="btn">Agregar sinodalía</button>
      <a class="btn" href="/siged/public/index.php?action=jefe_ver&id=<?= $sid ?>">Volver</a>
    </div>
  </form>

  <hr style="margin:1rem 0">

  <h4 style="margin:0 0 .5rem">Sinodalías capturadas</h4>

  <?php if (!$rows): ?>
    <div class="alert" style="background:#f9fafb;border:1px solid #e5e7eb;padding:.75rem;border-radius:.5rem;color:#4b5563">
      <i class='bx bx-info-circle'></i> No hay sinodalías capturadas aún para este documento.
    </div>
  <?php else: ?>
    <div style="overflow-x:auto">
      <table style="width:100%;border-collapse:collapse;font-size:.9rem">
        <thead>
          <tr style="background:#f3f4f6">
            <th style="border:1px solid #e5e7eb;padding:.4rem .5rem">#</th>
            <th style="border:1px solid #e5e7eb;padding:.4rem .5rem">Tipo</th>
            <th style="border:1px solid #e5e7eb;padding:.4rem .5rem">Estudiante</th>
            <th style="border:1px solid #e5e7eb;padding:.4rem .5rem">Programa</th>
            <th style="border:1px solid #e5e7eb;padding:.4rem .5rem">Folio acta</th>
            <th style="border:1px solid #e5e7eb;padding:.4rem .5rem">Fecha</th>
            <th style="border:1px solid #e5e7eb;padding:.4rem .5rem">Rol</th>
            <th style="border:1px solid #e5e7eb;padding:.4rem .5rem;text-align:center">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $idx => $c): ?>
            <tr>
              <td style="border:1px solid #e5e7eb;padding:.35rem .5rem;white-space:nowrap">
                <?= (int)($c['ORDEN'] ?? ($idx+1)) ?>
              </td>
              <td style="border:1px solid #e5e7eb;padding:.35rem .5rem">
                <?= htmlspecialchars((string)$c['TIPO_EXAMEN']) ?>
              </td>
              <td style="border:1px solid #e5e7eb;padding:.35rem .5rem">
                <?= htmlspecialchars((string)$c['NOMBRE_ESTUDIANTE']) ?>
              </td>
              <td style="border:1px solid #e5e7eb;padding:.35rem .5rem">
                <?= htmlspecialchars((string)$c['PROGRAMA_EDUCATIVO']) ?>
              </td>
              <td style="border:1px solid #e5e7eb;padding:.35rem .5rem">
                <?= htmlspecialchars((string)$c['FOLIO_ACTA']) ?>
              </td>
              <td style="border:1px solid #e5e7eb;padding:.35rem .5rem">
                <?= htmlspecialchars((string)$c['FECHA_EXAMEN']) ?>
              </td>
              <td style="border:1px solid #e5e7eb;padding:.35rem .5rem">
                <?= htmlspecialchars((string)$c['ROL_PARTICIPACION']) ?>
              </td>
              <td style="border:1px solid #e5e7eb;padding:.35rem .5rem;text-align:center">
                <form method="post"
                      action="/siged/public/index.php?action=cst_del"
                      style="display:inline"
                      onsubmit="return confirm('¿Eliminar esta sinodalía?');">
                  <input type="hidden" name="id" value="<?= $sid ?>">
                  <input type="hidden" name="sinodalia_id" value="<?= (int)$c['ID_SINODALIA'] ?>">
                  <button type="submit"
                          class="btn"
                          style="padding:.15rem .4rem;font-size:.8rem;background:#fee2e2;border:1px solid #fecaca;color:#b91c1c;border-radius:.35rem">
                    <i class='bx bx-trash'></i> Eliminar
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['cst_saved'])): ?>
    <div class="alert" style="margin-top:.5rem;background:#ecfdf3;border:1px solid #bbf7d0;color:#166534;padding:.5rem;border-radius:.5rem">
      Sinodalía guardada correctamente.
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['cst_err']) && $_GET['cst_err']==='campos'): ?>
    <div class="alert" style="margin-top:.5rem;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:.5rem;border-radius:.5rem">
      Por favor completa todos los campos para registrar la sinodalía.
    </div>
  <?php endif; ?>
</section>
