<?php
// app/php/cca_form.php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';

// Este form se monta sólo si $sol viene de cca_mount
if (!isset($sol) || strtoupper($sol['tipo'] ?? '') !== 'CCA') {
    return;
}

$pdo = DB::conn();

$sid       = (int)($sol['id'] ?? 0);
$idDocente = (int)($sol['id_docente'] ?? 0);

if ($sid <= 0 || $idDocente <= 0) return;

// IDs de periodo que usas en el PDF
$PER_ENE_JUN = 1; // ENE–JUN año anterior
$PER_AGO_DIC = 2; // AGO–DIC año anterior

$anioActual   = (int)date('Y');
$anioAnterior = $anioActual - 1;

// Traer carga existente (solo periodos 1 y 2, estado VIGENTE y detalle ACTIVO)
$st = $pdo->prepare("
    SELECT 
      C.ID_PERIODO,
      D.ID_DETALLE,
      D.ID_CARGA,
      D.ASIGNATURA,
      D.NIVEL,
      D.GRUPO,
      D.TIPO_ACTIVIDAD,
      D.HORAS_SEMANA,
      D.TOTAL_HORAS
    FROM dbo.CARGA_DOCENTE C
    JOIN dbo.CARGA_DETALLE D ON D.ID_CARGA = C.ID_CARGA
    WHERE C.ID_DOCENTE = :doc
      AND C.ESTADO     = 'VIGENTE'
      AND (C.ID_PERIODO = :p1 OR C.ID_PERIODO = :p2)
      AND D.ACTIVO      = 1
    ORDER BY C.ID_PERIODO, D.ASIGNATURA
");
$st->execute([
  ':doc' => $idDocente,
  ':p1'  => $PER_ENE_JUN,
  ':p2'  => $PER_AGO_DIC,
]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

// Agrupamos por periodo para mostrar dos tablas
$porPeriodo = [
  $PER_ENE_JUN => [],
  $PER_AGO_DIC => [],
];
foreach ($rows as $r) {
    $per = (int)$r['ID_PERIODO'];
    if (!isset($porPeriodo[$per])) {
        $porPeriodo[$per] = [];
    }
    $porPeriodo[$per][] = $r;
}

function etiquetaPeriodo(int $idPer, int $anioAnterior): string {
    return match($idPer) {
        1       => "ENERO – JUNIO $anioAnterior",
        2       => "AGOSTO – DICIEMBRE $anioAnterior",
        default => "Periodo ID $idPer",
    };
}
?>
<section class="card" style="margin-top:1rem;padding:1rem 1.25rem">
  <h3 style="margin:0 0 .75rem">Datos para Constancia de Carga Académica (CCA)</h3>

  <!-- Formulario para agregar una fila de carga -->
  <form method="post" action="/siged/public/index.php?action=cca_guardar" style="display:grid;gap:.75rem">
    <input type="hidden" name="id" value="<?= $sid ?>">

    <div style="display:grid;grid-template-columns:1.2fr 1fr 1fr;gap:.75rem">
      <label>Periodo
        <select name="id_periodo" required>
          <option value="">Seleccione...</option>
          <option value="<?= $PER_ENE_JUN ?>">
            <?= etiquetaPeriodo($PER_ENE_JUN, $anioAnterior) ?>
          </option>
          <option value="<?= $PER_AGO_DIC ?>">
            <?= etiquetaPeriodo($PER_AGO_DIC, $anioAnterior) ?>
          </option>
        </select>
      </label>
      <label>Nivel
        <input type="text" name="nivel" required placeholder="Lic., Ing., etc.">
      </label>
      <label>Grupo
        <input type="text" name="grupo" required placeholder="p.ej. A, B, 1A">
      </label>
    </div>

    <div style="display:grid;grid-template-columns:1.5fr 0.8fr 0.8fr 0.8fr;gap:.75rem">
      <label>Asignatura
        <input type="text" name="asignatura" required placeholder="p.ej. Cálculo Diferencial">
      </label>
      <label>Tipo de actividad
        <input type="text" name="tipo_actividad" required placeholder="FG, TUT, etc." value="FG">
      </label>
      <label>Horas / semana
        <input type="number" name="horas_semana" min="0" step="0.5" required value="0">
      </label>
      <label>Total horas
        <input type="number" name="total_horas" min="0" step="1" placeholder="Si lo dejas vacío se calcula">
      </label>
    </div>

    <div style="display:flex;gap:.5rem;margin-top:.5rem">
      <button type="submit" class="btn">Agregar registro</button>
      <a class="btn" href="/siged/public/index.php?action=jefe_ver&id=<?= $sid ?>">Volver</a>
    </div>
  </form>

  <hr style="margin:1rem 0">

  <!-- Tablas por periodo -->
  <?php foreach ([$PER_ENE_JUN, $PER_AGO_DIC] as $perId): ?>
    <h4 style="margin:.25rem 0 .5rem">
      <?= etiquetaPeriodo($perId, $anioAnterior) ?>
    </h4>

    <?php $lista = $porPeriodo[$perId] ?? []; ?>
    <?php if (!$lista): ?>
      <div class="alert" style="background:#f9fafb;border:1px solid #e5e7eb;padding:.6rem;border-radius:.5rem;color:#4b5563;margin-bottom:.75rem">
        <i class='bx bx-info-circle'></i> No hay registros capturados para este periodo.
      </div>
    <?php else: ?>
      <div style="overflow-x:auto;margin-bottom:1rem">
        <table style="width:100%;border-collapse:collapse;font-size:.9rem">
          <thead>
            <tr style="background:#f3f4f6">
              <th style="border:1px solid #e5e7eb;padding:.4rem .5rem">Asignatura</th>
              <th style="border:1px solid #e5e7eb;padding:.4rem .5rem">Nivel</th>
              <th style="border:1px solid #e5e7eb;padding:.4rem .5rem">Grupo</th>
              <th style="border:1px solid #e5e7eb;padding:.4rem .5rem">Tipo</th>
              <th style="border:1px solid #e5e7eb;padding:.4rem .5rem;text-align:center">H/sem</th>
              <th style="border:1px solid #e5e7eb;padding:.4rem .5rem;text-align:center">Total horas</th>
              <th style="border:1px solid #e5e7eb;padding:.4rem .5rem;text-align:center">Acciones</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($lista as $r): ?>
            <tr>
              <td style="border:1px solid #e5e7eb;padding:.35rem .5rem">
                <?= htmlspecialchars((string)$r['ASIGNATURA']) ?>
              </td>
              <td style="border:1px solid #e5e7eb;padding:.35rem .5rem">
                <?= htmlspecialchars((string)$r['NIVEL']) ?>
              </td>
              <td style="border:1px solid #e5e7eb;padding:.35rem .5rem">
                <?= htmlspecialchars((string)$r['GRUPO']) ?>
              </td>
              <td style="border:1px solid #e5e7eb;padding:.35rem .5rem">
                <?= htmlspecialchars((string)$r['TIPO_ACTIVIDAD']) ?>
              </td>
              <td style="border:1px solid #e5e7eb;padding:.35rem .5rem;text-align:center">
                <?= (float)$r['HORAS_SEMANA'] ?>
              </td>
              <td style="border:1px solid #e5e7eb;padding:.35rem .5rem;text-align:center">
                <?= (float)$r['TOTAL_HORAS'] ?>
              </td>
              <td style="border:1px solid #e5e7eb;padding:.35rem .5rem;text-align:center">
                <form method="post"
                      action="/siged/public/index.php?action=cca_det_del"
                      style="display:inline"
                      onsubmit="return confirm('¿Eliminar este registro de carga?');">
                  <input type="hidden" name="id" value="<?= $sid ?>">
                  <input type="hidden" name="detalle_id" value="<?= (int)$r['ID_DETALLE'] ?>">
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

  <?php endforeach; ?>

  <?php if (isset($_GET['cca_saved'])): ?>
    <div class="alert" style="margin-top:.5rem;background:#ecfdf3;border:1px solid #bbf7d0;color:#166534;padding:.5rem;border-radius:.5rem">
      Datos de carga guardados correctamente.
    </div>
  <?php endif; ?>
</section>
