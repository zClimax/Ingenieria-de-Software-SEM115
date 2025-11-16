<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';

// DESPUÉS (llaves normalizadas por el mount)
if (!isset($sol) || ($sol['tipo'] ?? '') !== 'ACI') return;

$pdo = DB::conn();

// Cargar registro existente (si lo hay)
$st = $pdo->prepare("SELECT TOP 1 * FROM dbo.DOCENTE_CI_CONST WHERE ID_SOLICITUD=:sid ORDER BY ID_CI DESC");
$st->execute([':sid'=>$sid]);
$rowCI = $st->fetch(PDO::FETCH_ASSOC);

// Valores por defecto
$val = function(string $k, $def='') use($rowCI){ return htmlspecialchars((string)($rowCI[$k] ?? $def)); };
?>
<section class="card" style="margin-top:1rem;padding:1rem 1.25rem">
  <h3 style="margin:0 0 .75rem">Datos para la Constancia (Centro de Información)</h3>
  <form method="post" action="/siged/public/index.php?action=ci_guardar" style="display:grid;gap:.75rem">
    <input type="hidden" name="id" value="<?= $sid ?>">

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
      <label>Actividad
        <input type="text" name="actividad" required placeholder="p.ej. Monitor de cine"
               value="<?= $val('ACTVIDAD') ?>">
      </label>
      <label>Periodo
        <input type="text" name="periodo" required placeholder="p.ej. enero-junio de 2024"
               value="<?= $val('PERIODO') ?>">
      </label>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.75rem">
      <label>Dictamen
        <input type="text" name="dictamen" placeholder="p.ej. DCINTFALLCIN2241"
               value="<?= $val('DICTAMEN') ?>">
      </label>
      <label>Alumnos atendidos (total)
        <input type="number" min="0" step="1" name="alumnos_total" required
               value="<?= $val('ALUMNOS_TOTAL','0') ?>">
      </label>
      <label>Con crédito
        <input type="number" min="0" step="1" name="alumnos_credito" required
               value="<?= $val('ALUMNOS_CREDITO','0') ?>">
      </label>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:.75rem">
      <label>No. de Oficio
        <input type="text" name="oficio_no" placeholder="p.ej. CI-030/2025"
               value="<?= $val('OFICIO_NO') ?>">
      </label>
      <label>Lugar
        <input type="text" name="lugar" required placeholder="Culiacán, Sinaloa"
               value="<?= $val('LUGAR','Culiacán, Sinaloa') ?>">
      </label>
      <label>Fecha del oficio
        <input type="date" name="fecha_oficio"
               value="<?= $val('FECHA_OFICIO') ?>">
      </label>
    </div>

    <div style="display:flex;gap:.5rem">
      <button type="submit" class="btn">Guardar datos</button>
      <a class="btn" href="/siged/public/index.php?action=jefe_ver&id=<?= $sid ?>">Cancelar</a>
    </div>
  </form>
  <?php if (isset($_GET['saved'])): ?>
    <div class="alert" style="margin-top:.5rem">Datos guardados correctamente.</div>
  <?php endif; ?>
</section>
