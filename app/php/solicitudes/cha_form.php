<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';

// Se asume montado por cha_mount.php
if (!isset($sol) || strtoupper($sol['tipo'] ?? '') !== 'CHA') {
    return;
}

$pdo = DB::conn();
$sid = (int)($sol['id'] ?? 0);
$idDocente = (int)($sol['id_docente'] ?? 0);

if ($sid <= 0 || $idDocente <= 0) {
    echo '<div class="alert" style="margin:.5rem 0;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:.5rem;border-radius:.5rem">
            Parámetros inválidos para CHA (solicitud o docente).
          </div>';
    return;
}

/**
 * 1) Traer ASIGNATURAS desde DOCENTE_CARGA,
 *    pero ligadas a CSE/CSE2 del mismo docente.
 */
$st = $pdo->prepare("
    SELECT 
        C.ID_CARGA,
        C.PERIODO,
        C.NIVEL,
        C.CLAVE_MATERIA,
        C.NOMBRE_MATERIA,
        C.ALUMNOS_ATENDIDOS,
        C.ORDEN,
        C.ID_SOLICITUD AS ID_SOL_ORIGEN
    FROM dbo.DOCENTE_CARGA C
    JOIN dbo.SOLICITUD_DOCUMENTO S ON S.ID_SOLICITUD = C.ID_SOLICITUD
    WHERE S.ID_DOCENTE = :doc
      AND S.TIPO_DOCUMENTO IN ('CSE','CSE2')
      AND UPPER(C.NIVEL) LIKE '%POSGRADO%'
    ORDER BY C.PERIODO, C.ORDEN, C.ID_CARGA
");

$st->execute([':doc' => $idDocente]);
$materias = $st->fetchAll(PDO::FETCH_ASSOC);

/**
 * 2) Traer HORARIOS ya capturados para ESTA solicitud CHA
 */
$stH = $pdo->prepare("
    SELECT 
        H.ID_HORARIO,
        H.SEMESTRE,
        H.DIAS_SEMANA,
        H.HORA_INICIO,
        H.HORA_FIN,
        H.AULA,
        C.NOMBRE_MATERIA,
        C.CLAVE_MATERIA,
        C.NIVEL,
        C.PERIODO
    FROM dbo.DOCENTE_CARGA_HORARIO H
    JOIN dbo.DOCENTE_CARGA C ON C.ID_CARGA = H.ID_CARGA
    WHERE H.ID_SOLICITUD = :sid
    ORDER BY H.SEMESTRE, C.NIVEL, C.NOMBRE_MATERIA, H.ID_HORARIO
");
$stH->execute([':sid' => $sid]);
$horarios = $stH->fetchAll(PDO::FETCH_ASSOC);
?>

<section class="card" style="margin-top:1rem;padding:1rem 1.25rem">
  <h3 style="margin:0 0 .75rem">
    Horarios de asignaturas de posgrado (CHA)
  </h3>

  <?php if (!$materias): ?>
    <div class="alert" style="background:#f9fafb;border:1px solid #e5e7eb;padding:.75rem;border-radius:.5rem;color:#4b5563">
      <i class='bx bx-info-circle'></i>
      No hay asignaturas registradas aún para este docente en CSE/CSE2.
      <br>
      Ve primero al apartado de captura de asignaturas (CSE / CSE2) y luego regresa aquí.
    </div>
    <a class="btn" href="/siged/public/index.php?action=jefe_ver&id=<?= $sid ?>">Volver</a>
    <?php return; ?>
  <?php endif; ?>

  <!-- Formulario para agregar horario a una asignatura -->
  <form method="post" action="/siged/public/index.php?action=cha_guardar" style="display:grid;gap:.75rem">
    <input type="hidden" name="id" value="<?= $sid ?>">

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
      <label>Asignatura (desde CSE/CSE2)
        <select name="carga_id" required>
          <option value="">-- Selecciona asignatura --</option>
          <?php foreach ($materias as $m): ?>
            <?php
              $label = $m['PERIODO'].' · '.$m['NIVEL'].' · '.$m['CLAVE_MATERIA'].' · '.$m['NOMBRE_MATERIA'].' (Alumnos: '.$m['ALUMNOS_ATENDIDOS'].')';
            ?>
            <option value="<?= (int)$m['ID_CARGA'] ?>">
              <?= htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>Semestre
        <select name="semestre" required>
          <option value="1">Primer semestre</option>
          <option value="2">Segundo semestre</option>
        </select>
      </label>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.75rem">
      <label>Días de la semana
        <input type="text" name="dias_semana" required placeholder="p.ej. Lun-Mié-Vie">
      </label>
      <label>Hora inicio
        <input type="time" name="hora_inicio" required>
      </label>
      <label>Hora fin
        <input type="time" name="hora_fin" required>
      </label>
    </div>

    <div style="max-width:240px">
      <label>Aula
        <input type="text" name="aula" placeholder="p.ej. A-204">
      </label>
    </div>

    <div style="display:flex;gap:.5rem;margin-top:.5rem">
      <button type="submit" class="btn">Agregar horario</button>
      <a class="btn" href="/siged/public/index.php?action=jefe_ver&id=<?= $sid ?>">Volver</a>
    </div>
  </form>

  <hr style="margin:1rem 0">

  <h4 style="margin:0 0 .5rem">Horarios capturados</h4>

  <?php if (!$horarios): ?>
    <div class="alert" style="background:#f9fafb;border:1px solid #e5e7eb;padding:.75rem;border-radius:.5rem;color:#4b5563">
      <i class='bx bx-info-circle'></i> No hay horarios capturados aún para este documento CHA.
    </div>
  <?php else: ?>
    <div style="overflow-x:auto">
      <table style="width:100%;border-collapse:collapse;font-size:.9rem">
      <thead>
  <tr style="background:#f3f4f6">
    <th style="border:1px solid #e5e7eb;padding:.4rem .5rem">Sem.</th>
    <th style="border:1px solid #e5e7eb;padding:.4rem .5rem">Periodo</th>
    <th style="border:1px solid #e5e7eb;padding:.4rem .5rem">Asignatura</th>
    <th style="border:1px solid #e5e7eb;padding:.4rem .5rem">Clave</th>
    <th style="border:1px solid #e5e7eb;padding:.4rem .5rem">Días</th>
    <th style="border:1px solid #e5e7eb;padding:.4rem .5rem">Horario</th>
    <th style="border:1px solid #e5e7eb;padding:.4rem .5rem">Aula</th>
    <th style="border:1px solid #e5e7eb;padding:.4rem .5rem;text-align:center">Acciones</th>
  </tr>
</thead>
        <tbody>
          <?php foreach ($horarios as $h): ?>
            <tr>
  <td style="border:1px solid #e5e7eb;padding:.35rem .5rem;text-align:center">
    <?= (int)$h['SEMESTRE'] ?>
  </td>
  <td style="border:1px solid #e5e7eb;padding:.35rem .5rem">
    <?= htmlspecialchars((string)$h['PERIODO']) ?>
  </td>
  <td style="border:1px solid #e5e7eb;padding:.35rem .5rem">
    <?= htmlspecialchars((string)$h['NOMBRE_MATERIA']) ?>
  </td>
  <td style="border:1px solid #e5e7eb;padding:.35rem .5rem">
    <?= htmlspecialchars((string)$h['CLAVE_MATERIA']) ?>
  </td>
  <td style="border:1px solid #e5e7eb;padding:.35rem .5rem">
    <?= htmlspecialchars((string)$h['DIAS_SEMANA']) ?>
  </td>
  <td style="border:1px solid #e5e7eb;padding:.35rem .5rem">
    <?= substr((string)$h['HORA_INICIO'],0,5).' - '.substr((string)$h['HORA_FIN'],0,5) ?>
  </td>
  <td style="border:1px solid #e5e7eb;padding:.35rem .5rem">
    <?= htmlspecialchars((string)($h['AULA'] ?? '')) ?>
  </td>
  <td style="border:1px solid #e5e7eb;padding:.35rem .5rem;text-align:center">
    <form method="post"
          action="/siged/public/index.php?action=cha_del"
          style="display:inline"
          onsubmit="return confirm('¿Eliminar este horario?');">
      <input type="hidden" name="id" value="<?= $sid ?>">
      <input type="hidden" name="horario_id" value="<?= (int)$h['ID_HORARIO'] ?>">
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

  <?php if (isset($_GET['cha_saved'])): ?>
    <div class="alert" style="margin-top:.5rem;background:#ecfdf3;border:1px solid #bbf7d0;color:#166534;padding:.5rem;border-radius:.5rem">
      Horario guardado correctamente.
    </div>
  <?php endif; ?>
</section>
