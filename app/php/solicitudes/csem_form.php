<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';

// Montado por csem_mount.php
if (!isset($sol) || strtoupper($sol['tipo'] ?? '') !== 'CSEM') {
  return;
}

$pdo = DB::conn();
$sid = (int)($sol['id'] ?? 0);
if ($sid <= 0) return;

// Registros ligados a ESTA solicitud CSEM
$st = $pdo->prepare("
  SELECT ID_CARGA_MODALIDAD, PERIODO, NIVEL, CLAVE_MATERIA,
         NOMBRE_MATERIA,
         ALUMNOS_ESCOLARIZADA, ALUMNOS_NO_ESCOLARIZADA, ALUMNOS_MIXTA,
         ORDEN
  FROM dbo.DOCENTE_CARGA_MODALIDAD
  WHERE ID_SOLICITUD = :sid
  ORDER BY ORDEN, ID_CARGA_MODALIDAD
");
$st->execute([':sid' => $sid]);
$regs = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<section class="card" style="margin-top:1rem;padding:1rem 1.25rem">
  <h3 style="margin:0 0 .75rem">Modalidades de atención (CSEM)</h3>

  <form method="post" action="/siged/public/index.php?action=csem_guardar" style="display:grid;gap:.75rem">
    <input type="hidden" name="id" value="<?= $sid ?>">

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
      <label>Periodo
        <input type="text" name="periodo" required placeholder="p.ej. Primer semestre 2024">
      </label>
      <label>Nivel
        <input type="text" name="nivel" required placeholder="p.ej. Posgrado, Licenciatura, etc.">
      </label>
    </div>

    <div style="display:grid;grid-template-columns:1fr 2fr;gap:.75rem">
      <label>Clave de asignatura
        <input type="text" name="clave_materia" required placeholder="p.ej. IA01">
      </label>
      <label>Nombre de asignatura
        <input type="text" name="nombre_materia" required placeholder="p.ej. Inteligencia Artificial">
      </label>
    </div>

    <div style="display:grid;grid-template-columns:repeat(3, minmax(0, 1fr));gap:.75rem;max-width:520px">
      <label>Escolarizada (estudiantes)
        <input type="number" name="esc" min="0" step="1" required value="0">
      </label>
      <label>No escolarizada (estudiantes)
        <input type="number" name="noesc" min="0" step="1" required value="0">
      </label>
      <label>Mixta (estudiantes)
        <input type="number" name="mix" min="0" step="1" required value="0">
      </label>
    </div>

    <div style="display:flex;gap:.5rem;margin-top:.5rem">
      <button type="submit" class="btn">Agregar registro</button>
      <a class="btn" href="/siged/public/index.php?action=jefe_ver&id=<?= $sid ?>">Volver</a>
    </div>
  </form>

  <hr style="margin:1rem 0">

  <h4 style="margin:0 0 .5rem">Registros capturados</h4>

  <?php if (!$regs): ?>
    <div class="alert" style="background:#f9fafb;border:1px solid #e5e7eb;padding:.75rem;border-radius:.5rem;color:#4b5563">
      <i class='bx bx-info-circle'></i> No hay registros de modalidades capturados aún para este documento.
    </div>
  <?php else: ?>
    <div style="overflow-x:auto">
      <table style="width:100%;border-collapse:collapse;font-size:.9rem">
        <thead>
          <tr style="background:#f3f4f6">
            <th style="border:1px solid #e5e7eb;padding:.4rem .5rem">#</th>
            <th style="border:1px solid #e5e7eb;padding:.4rem .5rem">Periodo</th>
            <th style="border:1px solid #e5e7eb;padding:.4rem .5rem">Nivel</th>
            <th style="border:1px solid #e5e7eb;padding:.4rem .5rem">Clave</th>
            <th style="border:1px solid #e5e7eb;padding:.4rem .5rem">Asignatura</th>
            <th style="border:1px solid #e5e7eb;padding:.4rem .5rem;text-align:center">Escolarizada</th>
            <th style="border:1px solid #e5e7eb;padding:.4rem .5rem;text-align:center">No escolarizada</th>
            <th style="border:1px solid #e5e7eb;padding:.4rem .5rem;text-align:center">Mixta</th>
            <th style="border:1px solid #e5e7eb;padding:.4rem .5rem;text-align:center">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($regs as $idx => $c): ?>
            <tr>
              <td style="border:1px solid #e5e7eb;padding:.35rem .5rem;white-space:nowrap">
                <?= (int)($c['ORDEN'] ?? ($idx+1)) ?>
              </td>
              <td style="border:1px solid #e5e7eb;padding:.35rem .5rem">
                <?= htmlspecialchars((string)$c['PERIODO']) ?>
              </td>
              <td style="border:1px solid #e5e7eb;padding:.35rem .5rem">
                <?= htmlspecialchars((string)$c['NIVEL']) ?>
              </td>
              <td style="border:1px solid #e5e7eb;padding:.35rem .5rem">
                <?= htmlspecialchars((string)$c['CLAVE_MATERIA']) ?>
              </td>
              <td style="border:1px solid #e5e7eb;padding:.35rem .5rem">
                <?= htmlspecialchars((string)$c['NOMBRE_MATERIA']) ?>
              </td>
              <td style="border:1px solid #e5e7eb;padding:.35rem .5rem;text-align:center">
                <?= (int)$c['ALUMNOS_ESCOLARIZADA'] ?>
              </td>
              <td style="border:1px solid #e5e7eb;padding:.35rem .5rem;text-align:center">
                <?= (int)$c['ALUMNOS_NO_ESCOLARIZADA'] ?>
              </td>
              <td style="border:1px solid #e5e7eb;padding:.35rem .5rem;text-align:center">
                <?= (int)$c['ALUMNOS_MIXTA'] ?>
              </td>
              <td style="border:1px solid #e5e7eb;padding:.35rem .5rem;text-align:center">
                <form method="post"
                      action="/siged/public/index.php?action=csem_del"
                      style="display:inline"
                      onsubmit="return confirm('¿Eliminar este registro?');">
                  <input type="hidden" name="id" value="<?= $sid ?>">
                  <input type="hidden" name="carga_id" value="<?= (int)$c['ID_CARGA_MODALIDAD'] ?>">
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

  <?php if (isset($_GET['csem_saved'])): ?>
    <div class="alert" style="margin-top:.5rem;background:#ecfdf3;border:1px solid #bbf7d0;color:#166534;padding:.5rem;border-radius:.5rem">
      Registro guardado correctamente.
    </div>
  <?php endif; ?>
</section>
