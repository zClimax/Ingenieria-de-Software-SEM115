<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';

// Montado por cpi_mount.php
if (!isset($sol) || strtoupper($sol['tipo'] ?? '') !== 'CPI') {
  return;
}

$pdo = DB::conn();
$sid = (int)($sol['id'] ?? 0);
if ($sid <= 0) return;

// Buscar si ya existe registro del proyecto integrador
$st = $pdo->prepare("
  SELECT ID_PROYECTO, NOMBRE_PROYECTO, LISTA_ASIGNATURAS, NIVEL
  FROM dbo.DOCENTE_PROYECTO_INTEGRADOR
  WHERE ID_SOLICITUD = :sid
");
$st->execute([':sid' => $sid]);
$P = $st->fetch(PDO::FETCH_ASSOC);

$nombreProyecto = $P['NOMBRE_PROYECTO'] ?? '';
$listaAsig      = $P['LISTA_ASIGNATURAS'] ?? '';
$nivel          = $P['NIVEL'] ?? '';
$idProyecto     = (int)($P['ID_PROYECTO'] ?? 0);
?>
<section class="card" style="margin-top:1rem;padding:1rem 1.25rem">
  <h3 style="margin:0 0 .75rem">Proyecto integrador (CPI)</h3>

  <form method="post" action="/siged/public/index.php?action=cpi_guardar" style="display:grid;gap:.75rem">
    <input type="hidden" name="id" value="<?= $sid ?>">
    <input type="hidden" name="id_proyecto" value="<?= $idProyecto ?>">

    <div style="display:grid;grid-template-columns:1.5fr 1fr;gap:.75rem">
      <label>Nombre del proyecto integrador
        <input type="text" name="nombre_proyecto" required
               value="<?= htmlspecialchars($nombreProyecto) ?>"
               placeholder="p.ej. Proyecto integrador de sistemas embebidos">
      </label>
      <label>Nivel
        <input type="text" name="nivel"
               value="<?= htmlspecialchars($nivel) ?>"
               placeholder="p.ej. Licenciatura, Posgrado, etc.">
      </label>
    </div>

    <label>Asignaturas que conforman el proyecto
      <textarea name="lista_asignaturas" rows="3" required
                placeholder="p.ej. Cálculo Integral; Programación Orientada a Objetos; Taller de Ética"><?= htmlspecialchars($listaAsig) ?></textarea>
    </label>

    <div style="display:flex;gap:.5rem;margin-top:.5rem">
      <button type="submit" class="btn">Guardar proyecto integrador</button>
      <a class="btn" href="/siged/public/index.php?action=jefe_ver&id=<?= $sid ?>">Volver</a>
    </div>
  </form>

  <?php if (isset($_GET['cpi_saved'])): ?>
    <div class="alert" style="margin-top:.5rem;background:#ecfdf3;border:1px solid #bbf7d0;color:#166534;padding:.5rem;border-radius:.5rem">
      Proyecto integrador guardado correctamente.
    </div>
  <?php endif; ?>
</section>
