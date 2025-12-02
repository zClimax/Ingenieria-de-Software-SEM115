<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';

// Montado por cpp_mount.php
if (!isset($sol) || strtoupper($sol['tipo'] ?? '') !== 'CPP') {
  return;
}

$pdo = DB::conn();
$sid = (int)($sol['id'] ?? 0);
if ($sid <= 0) return;

// Buscar si ya existe registro
$st = $pdo->prepare("
  SELECT ID_PROYECTO_PREMIADO,
         NOMBRE_PROYECTO,
         LUGAR_OBTENIDO,
         NOMBRE_CONCURSO
  FROM dbo.DOCENTE_PROYECTO_PREMIADO
  WHERE ID_SOLICITUD = :sid
");
$st->execute([':sid' => $sid]);
$P = $st->fetch(PDO::FETCH_ASSOC);

$idProj        = (int)($P['ID_PROYECTO_PREMIADO'] ?? 0);
$nombreProyecto= $P['NOMBRE_PROYECTO']   ?? '';
$lugarObtenido = $P['LUGAR_OBTENIDO']    ?? '';
$nombreConcurso= $P['NOMBRE_CONCURSO']   ?? '';
?>
<section class="card" style="margin-top:1rem;padding:1rem 1.25rem">
  <h3 style="margin:0 0 .75rem">Proyecto premiado en concurso/evento (CPP)</h3>

  <form method="post"
        action="/siged/public/index.php?action=cpp_guardar"
        style="display:grid;gap:.75rem">
    <input type="hidden" name="id" value="<?= $sid ?>">
    <input type="hidden" name="id_proyecto" value="<?= $idProj ?>">

    <label>Nombre del proyecto
      <input type="text"
             name="nombre_proyecto"
             required
             value="<?= htmlspecialchars($nombreProyecto) ?>"
             placeholder="p.ej. Sistema inteligente de monitoreo de cultivos">
    </label>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
      <label>Lugar obtenido
        <select name="lugar_obtenido" required>
          <option value="">Seleccione...</option>
          <option value="primer"  <?= $lugarObtenido==='primer'  ? 'selected' : '' ?>>Primer lugar</option>
          <option value="segundo" <?= $lugarObtenido==='segundo' ? 'selected' : '' ?>>Segundo lugar</option>
          <option value="tercer"  <?= $lugarObtenido==='tercer'  ? 'selected' : '' ?>>Tercer lugar</option>
          <option value="mención honorífica" <?= $lugarObtenido==='mención honorífica' ? 'selected' : '' ?>>Mención honorífica</option>
          <option value="otro" <?= ($lugarObtenido!=='' && !in_array($lugarObtenido, ['primer','segundo','tercer','mención honorífica'])) ? 'selected' : '' ?>>Otro (especifique en el PDF)</option>
        </select>
      </label>

      <label>Nombre del concurso
        <input type="text"
               name="nombre_concurso"
               required
               value="<?= htmlspecialchars($nombreConcurso) ?>"
               placeholder="p.ej. ENECB-CEA 2024">
      </label>
    </div>

    <div style="display:flex;gap:.5rem;margin-top:.5rem">
      <button type="submit" class="btn">Guardar datos</button>
      <a class="btn" href="/siged/public/index.php?action=jefe_ver&id=<?= $sid ?>">Volver</a>
    </div>
  </form>

  <?php if (isset($_GET['cpp_saved'])): ?>
    <div class="alert" style="margin-top:.5rem;background:#ecfdf3;border:1px solid #bbf7d0;color:#166534;padding:.5rem;border-radius:.5rem">
      Información del proyecto premiado guardada correctamente.
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['cpp_err']) && $_GET['cpp_err']==='campos'): ?>
    <div class="alert" style="margin-top:.5rem;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:.5rem;border-radius:.5rem">
      Por favor completa todos los campos del proyecto premiado.
    </div>
  <?php endif; ?>
</section>
