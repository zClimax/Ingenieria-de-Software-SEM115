<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';

// Montado por cjea_mount.php
if (!isset($sol) || strtoupper($sol['tipo'] ?? '') !== 'CJEA') {
  return;
}

$pdo = DB::conn();
$sid = (int)($sol['id'] ?? 0);
if ($sid <= 0) return;

// Buscar si ya existe registro
$st = $pdo->prepare("
  SELECT ID_JURADO_EVENTO,
         NOMBRE_EVENTO,
         FECHA_EVENTO,
         LUGAR_EVENTO
  FROM dbo.DOCENTE_JURADO_EVENTO
  WHERE ID_SOLICITUD = :sid
");
$st->execute([':sid' => $sid]);
$J = $st->fetch(PDO::FETCH_ASSOC);

$idJurado     = (int)($J['ID_JURADO_EVENTO'] ?? 0);
$nombreEvento = $J['NOMBRE_EVENTO'] ?? '';
$lugarEvento  = $J['LUGAR_EVENTO']  ?? '';
$fechaEvento  = '';
if (!empty($J['FECHA_EVENTO'])) {
  // Formato yyyy-mm-dd para input date
  $fechaEvento = substr((string)$J['FECHA_EVENTO'], 0, 10);
}
?>
<section class="card" style="margin-top:1rem;padding:1rem 1.25rem">
  <h3 style="margin:0 0 .75rem">Comisión como jurado en evento académico (CJEA)</h3>

  <form method="post"
        action="/siged/public/index.php?action=cjea_guardar"
        style="display:grid;gap:.75rem">
    <input type="hidden" name="id" value="<?= $sid ?>">
    <input type="hidden" name="id_jurado" value="<?= $idJurado ?>">

    <label>Nombre del evento académico
      <input type="text"
             name="nombre_evento"
             required
             value="<?= htmlspecialchars($nombreEvento) ?>"
             placeholder='p.ej. "Concurso Nacional de Innovación Tecnológica"'>
    </label>

    <div style="display:grid;grid-template-columns:1fr 2fr;gap:.75rem">
      <label>Fecha del evento
        <input type="date"
               name="fecha_evento"
               required
               value="<?= htmlspecialchars($fechaEvento) ?>">
      </label>

      <label>Lugar (sede)
        <input type="text"
               name="lugar_evento"
               required
               value="<?= htmlspecialchars($lugarEvento) ?>"
               placeholder="p.ej. TecNM Campus Culiacán, Culiacán, Sinaloa">
      </label>
    </div>

    <div style="display:flex;gap:.5rem;margin-top:.5rem">
      <button type="submit" class="btn">Guardar datos</button>
      <a class="btn" href="/siged/public/index.php?action=jefe_ver&id=<?= $sid ?>">Volver</a>
    </div>
  </form>

  <?php if (isset($_GET['cjea_saved'])): ?>
    <div class="alert" style="margin-top:.5rem;background:#ecfdf3;border:1px solid #bbf7d0;color:#166534;padding:.5rem;border-radius:.5rem">
      Información de jurado en evento guardada correctamente.
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['cjea_err']) && $_GET['cjea_err']==='campos'): ?>
    <div class="alert" style="margin-top:.5rem;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:.5rem;border-radius:.5rem">
      Por favor completa todos los campos del evento.
    </div>
  <?php endif; ?>
</section>
