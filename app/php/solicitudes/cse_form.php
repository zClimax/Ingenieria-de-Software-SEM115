<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';

// Este form se asume montado por cse_mount.php
if (!isset($sol)) {
    return;
}

$tipoDoc = strtoupper($sol['tipo'] ?? '');
if (!in_array($tipoDoc, ['CSE', 'CSE2'], true)) {
    // Solo se monta para CSE y CSE2
    return;
}

$pdo = DB::conn();
$sid = (int)($sol['id'] ?? 0);
if ($sid <= 0) return;

// Traer registros ya capturados en DOCENTE_CARGA
$st = $pdo->prepare("
    SELECT ID_CARGA, PERIODO, NIVEL, CLAVE_MATERIA, NOMBRE_MATERIA, ALUMNOS_ATENDIDOS, ORDEN
    FROM dbo.DOCENTE_CARGA
    WHERE ID_SOLICITUD = :sid
    ORDER BY ORDEN, ID_CARGA
");
$st->execute([':sid' => $sid]);
$cargas = $st->fetchAll(PDO::FETCH_ASSOC);

$isCSE   = ($tipoDoc === 'CSE');
$maxCSE  = 6;
$numRegs = count($cargas);
$limiteAlcanzado = $isCSE && $numRegs >= $maxCSE;

$titulo = $isCSE
    ? 'Datos para Constancia de Servicios Escolares (CSE)'
    : 'Datos para Constancia de séptima asignatura (CSE2)';

$ayuda = $isCSE
    ? 'En esta constancia solo se pueden registrar hasta 6 asignaturas. Si el docente tiene una séptima diferente, genera una solicitud CSE2 para las adicionales.'
    : 'Esta constancia corresponde a asignaturas adicionales (a partir de la séptima diferente) conforme al rubro 1.1.2 de la convocatoria.';

$err = $_GET['cse_err'] ?? '';
?>
<section class="card" style="margin-top:1rem;padding:1rem 1.25rem">
  <h3 style="margin:0 0 .5rem"><?= htmlspecialchars($titulo) ?></h3>
  <p style="margin:0 0 .75rem;font-size:.85rem;color:#4b5563">
    <?= htmlspecialchars($ayuda) ?><br>
    <?php if ($isCSE): ?>
      <strong>Registros actuales:</strong> <?= (int)$numRegs ?> / <?= $maxCSE ?>.
    <?php endif; ?>
  </p>

  <?php if ($err === 'campos'): ?>
    <div class="alert" style="background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:.5rem;border-radius:.5rem;margin-bottom:.5rem">
      <i class='bx bx-error-circle'></i> Completa todos los campos obligatorios antes de guardar.
    </div>
  <?php elseif ($err === 'limite'): ?>
    <div class="alert" style="background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:.5rem;border-radius:.5rem;margin-bottom:.5rem">
      <i class='bx bx-error-circle'></i> Esta constancia CSE ya tiene 6 asignaturas registradas. Para una séptima diferente, genera una solicitud de tipo CSE2.
    </div>
  <?php endif; ?>

  <?php if ($limiteAlcanzado): ?>
    <div class="alert" style="background:#f9fafb;border:1px solid #e5e7eb;padding:.75rem;border-radius:.5rem;color:#374151;margin-bottom:.75rem">
      Ya alcanzaste el máximo de <strong>6 asignaturas</strong> para esta solicitud CSE. 
      Puedes eliminar alguna fila si necesitas corregirla, o generar un documento <strong>CSE2</strong> para registrar asignaturas adicionales.
    </div>
  <?php else: ?>
    <!-- Formulario para agregar registro de carga -->
    <form method="post" action="/siged/public/index.php?action=cse_guardar" style="display:grid;gap:.75rem">
      <input type="hidden" name="id" value="<?= $sid ?>">

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
        <label>Periodo
          <input type="text" name="periodo" required placeholder="p.ej. 2024-ENE-JUN">
        </label>
        <label>Nivel
          <input type="text" name="nivel" required placeholder="Lic., Ing., Posgrado...">
        </label>
      </div>

      <div style="display:grid;grid-template-columns:1fr 2fr;gap:.75rem">
        <label>Clave de materia
          <input type="text" name="clave_materia" required placeholder="p.ej. MAT101">
        </label>
        <label>Nombre de materia
          <input type="text" name="nombre_materia" required placeholder="p.ej. Cálculo Diferencial">
        </label>
      </div>

      <div style="display:grid;grid-template-columns:1fr;gap:.75rem;max-width:220px">
        <label>Alumnos atendidos
          <input type="number" name="alumnos" min="0" step="1" required value="0">
        </label>
      </div>

      <div style="display:flex;gap:.5rem;margin-top:.5rem">
        <button type="submit" class="btn">Agregar registro</button>
        <a class="btn" href="/siged/public/index.php?action=jefe_ver&id=<?= $sid ?>">Volver</a>
      </div>
    </form>
  <?php endif; ?>

  <!-- Tabla de registros ya capturados -->
  <hr style="margin:1rem 0">

  <h4 style="margin:0 0 .5rem">Registros capturados</h4>

  <?php if (!$cargas): ?>
    <div class="alert" style="background:#f9fafb;border:1px solid #e5e7eb;padding:.75rem;border-radius:.5rem;color:#4b5563">
      <i class='bx bx-info-circle'></i> No hay registros de carga capturados aún.
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
            <th style="border:1px solid #e5e7eb;padding:.4rem .5rem">Materia</th>
            <th style="border:1px solid #e5e7eb;padding:.4rem .5rem;text-align:center">Alumnos</th>
            <th style="border:1px solid #e5e7eb;padding:.4rem .5rem;text-align:center">Acciones</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($cargas as $idx => $c): ?>
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
              <?= (int)$c['ALUMNOS_ATENDIDOS'] ?>
            </td>
            <td style="border:1px solid #e5e7eb;padding:.35rem .5rem;text-align:center">
              <form method="post" action="/siged/public/index.php?action=cse_del" 
                    style="display:inline"
                    onsubmit="return confirm('¿Eliminar este registro de carga?');">
                <input type="hidden" name="id" value="<?= $sid ?>">
                <input type="hidden" name="carga_id" value="<?= (int)$c['ID_CARGA'] ?>">
                <button type="submit" class="btn" style="padding:.15rem .4rem;font-size:.8rem;background:#fee2e2;border:1px solid #fecaca;color:#b91c1c;border-radius:.35rem">
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

  <?php if (isset($_GET['cse_saved'])): ?>
    <div class="alert" style="margin-top:.5rem;background:#ecfdf3;border:1px solid #bbf7d0;color:#166534;padding:.5rem;border-radius:.5rem">
      Datos de carga guardados correctamente.
    </div>
  <?php endif; ?>
</section>
