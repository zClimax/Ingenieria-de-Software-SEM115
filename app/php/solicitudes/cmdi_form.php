<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';

// Montado por cmdi_mount.php
if (!isset($sol) || strtoupper($sol['tipo'] ?? '') !== 'CMDI') {
  return;
}

$pdo = DB::conn();
$sid = (int)($sol['id'] ?? 0);
if ($sid <= 0) return;

// Buscar si ya existe registro
$st = $pdo->prepare("
  SELECT ID_MATERIAL, ENFOQUE, LISTA_PRODUCTOS, DESCRIPCION_IMPACTO
  FROM dbo.DOCENTE_MATERIALES_INCLUSIVOS
  WHERE ID_SOLICITUD = :sid
");
$st->execute([':sid' => $sid]);
$M = $st->fetch(PDO::FETCH_ASSOC);

$idMat   = (int)($M['ID_MATERIAL'] ?? 0);
$enfoque = $M['ENFOQUE'] ?? '';
$lista   = $M['LISTA_PRODUCTOS'] ?? '';
$impacto = $M['DESCRIPCION_IMPACTO'] ?? '';
?>
<section class="card" style="margin-top:1rem;padding:1rem 1.25rem">
  <h3 style="margin:0 0 .75rem">Materiales didácticos inclusivos (CMDI)</h3>

  <form method="post" action="/siged/public/index.php?action=cmdi_guardar" style="display:grid;gap:.75rem">
    <input type="hidden" name="id" value="<?= $sid ?>">
    <input type="hidden" name="id_material" value="<?= $idMat ?>">

    <label>Enfoque del material
      <select name="enfoque" required>
        <option value="">Seleccione una opción</option>
        <option value="intercultural" <?= $enfoque==='intercultural'?'selected':'' ?>>Intercultural</option>
        <option value="incluyente" <?= $enfoque==='incluyente'?'selected':'' ?>>Incluyente</option>
        <option value="responsabilidad social" <?= $enfoque==='responsabilidad social'?'selected':'' ?>>Responsabilidad social</option>
      </select>
    </label>

    <label>Productos obtenidos
      <textarea name="lista_productos" rows="3" required
                placeholder="Un producto por línea, p.ej. guía de actividades, video, infografía"><?= htmlspecialchars($lista) ?></textarea>
    </label>

    <label>Descripción del impacto en las experiencias de aprendizaje
      <textarea name="descripcion_impacto" rows="3" required
                placeholder="Describe de forma breve y clara el impacto observado en las experiencias de aprendizaje"><?= htmlspecialchars($impacto) ?></textarea>
    </label>

    <div style="display:flex;gap:.5rem;margin-top:.5rem">
      <button type="submit" class="btn">Guardar información</button>
      <a class="btn" href="/siged/public/index.php?action=jefe_ver&id=<?= $sid ?>">Volver</a>
    </div>
  </form>

  <?php if (isset($_GET['cmdi_saved'])): ?>
    <div class="alert" style="margin-top:.5rem;background:#ecfdf3;border:1px solid #bbf7d0;color:#166534;padding:.5rem;border-radius:.5rem">
      Información guardada correctamente.
    </div>
  <?php endif; ?>
</section>
