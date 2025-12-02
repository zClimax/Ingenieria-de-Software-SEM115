<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';

// Montado por cdre_mount.php
if (!isset($sol) || strtoupper($sol['tipo'] ?? '') !== 'CDRE') {
  return;
}

$pdo = DB::conn();
$sid = (int)($sol['id'] ?? 0);
if ($sid <= 0) return;

// Buscar si ya existe registro
$st = $pdo->prepare("
  SELECT ID_REA_DIPLOMADO, NOMBRE_MODULO
  FROM dbo.DOCENTE_DIPLOMADO_REA
  WHERE ID_SOLICITUD = :sid
");
$st->execute([':sid' => $sid]);
$R = $st->fetch(PDO::FETCH_ASSOC);

$idReg  = (int)($R['ID_REA_DIPLOMADO'] ?? 0);
$nomMod = $R['NOMBRE_MODULO'] ?? '';
?>
<section class="card" style="margin-top:1rem;padding:1rem 1.25rem">
  <h3 style="margin:0 0 .75rem">Diplomado Recursos Educativos en Ambientes Virtuales (CDRE)</h3>

  <form method="post"
        action="/siged/public/index.php?action=cdre_guardar"
        style="display:grid;gap:.75rem">
    <input type="hidden" name="id" value="<?= $sid ?>">
    <input type="hidden" name="id_rea_diplomado" value="<?= $idReg ?>">

    <label>Nombre del módulo
      <input type="text"
             name="nombre_modulo"
             required
             value="<?= htmlspecialchars($nomMod) ?>"
             placeholder='p.ej. "Diseño de recursos educativos en ambientes virtuales"'>
    </label>

    <div style="display:flex;gap:.5rem;margin-top:.5rem">
      <button type="submit" class="btn">Guardar datos</button>
      <a class="btn" href="/siged/public/index.php?action=jefe_ver&id=<?= $sid ?>">Volver</a>
    </div>
  </form>

  <?php if (isset($_GET['cdre_saved'])): ?>
    <div class="alert" style="margin-top:.5rem;background:#ecfdf3;border:1px solid #bbf7d0;color:#166534;padding:.5rem;border-radius:.5rem">
      Información del módulo guardada correctamente.
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['cdre_err']) && $_GET['cdre_err']==='campos'): ?>
    <div class="alert" style="margin-top:.5rem;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:.5rem;border-radius:.5rem">
      Por favor captura el nombre del módulo.
    </div>
  <?php endif; ?>
</section>
