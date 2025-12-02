<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';

// Montado por cce_mount.php
if (!isset($sol) || strtoupper($sol['tipo'] ?? '') !== 'CCE') {
  return;
}

$pdo = DB::conn();
$sid = (int)($sol['id'] ?? 0);
if ($sid <= 0) return;

// Buscar si ya existe registro
$st = $pdo->prepare("
  SELECT ID_COORD_EVENTO,
         TIPO_PARTICIPACION,
         NOMBRE_EVENTO,
         FUNCIONES,
         ACTIVIDADES
  FROM dbo.DOCENTE_COORD_EVENTO
  WHERE ID_SOLICITUD = :sid
");
$st->execute([':sid' => $sid]);
$C = $st->fetch(PDO::FETCH_ASSOC);

$idCoord       = (int)($C['ID_COORD_EVENTO'] ?? 0);
$tipoPart      = $C['TIPO_PARTICIPACION'] ?? '';
$nombreEvento  = $C['NOMBRE_EVENTO']      ?? '';
$funciones     = $C['FUNCIONES']          ?? '';
$actividades   = $C['ACTIVIDADES']        ?? '';
?>
<section class="card" style="margin-top:1rem;padding:1rem 1.25rem">
  <h3 style="margin:0 0 .75rem">Coordinación / colaboración en evento (CCE)</h3>

  <form method="post"
        action="/siged/public/index.php?action=cce_guardar"
        style="display:grid;gap:.75rem">
    <input type="hidden" name="id" value="<?= $sid ?>">
    <input type="hidden" name="id_coord" value="<?= $idCoord ?>">

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
      <label>Tipo de participación
        <select name="tipo_participacion" required>
          <option value="">Seleccione...</option>
          <option value="coordinación" <?= $tipoPart==='coordinación' ? 'selected' : '' ?>>Coordinación</option>
          <option value="colaboración" <?= $tipoPart==='colaboración' ? 'selected' : '' ?>>Colaboración</option>
        </select>
      </label>

      <label>Nombre del evento
        <input type="text"
               name="nombre_evento"
               required
               value="<?= htmlspecialchars($nombreEvento) ?>"
               placeholder="p.ej. InnovaTecNM 2024">
      </label>
    </div>

    <label>Funciones desempeñadas
      <textarea name="funciones"
                required
                rows="3"
                placeholder="Describe brevemente las funciones (p.ej. coordinación general, logística, evaluación de proyectos, etc.)"><?= htmlspecialchars($funciones) ?></textarea>
    </label>

    <label>Actividades desarrolladas
      <textarea name="actividades"
                required
                rows="4"
                placeholder="Describe las principales actividades realizadas durante el evento"><?= htmlspecialchars($actividades) ?></textarea>
    </label>

    <div style="display:flex;gap:.5rem;margin-top:.5rem">
      <button type="submit" class="btn">Guardar datos</button>
      <a class="btn" href="/siged/public/index.php?action=jefe_ver&id=<?= $sid ?>">Volver</a>
    </div>
  </form>

  <?php if (isset($_GET['cce_saved'])): ?>
    <div class="alert" style="margin-top:.5rem;background:#ecfdf3;border:1px solid #bbf7d0;color:#166534;padding:.5rem;border-radius:.5rem">
      Información de coordinación / colaboración guardada correctamente.
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['cce_err']) && $_GET['cce_err']==='campos'): ?>
    <div class="alert" style="margin-top:.5rem;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:.5rem;border-radius:.5rem">
      Por favor completa todos los campos del evento.
    </div>
  <?php endif; ?>
</section>
