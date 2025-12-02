<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';

// Montado por pasg_mount.php
if (!isset($sol) || strtoupper($sol['tipo'] ?? '') !== 'PASG') {
  return;
}

$pdo = DB::conn();
$sid = (int)($sol['id'] ?? 0);
if ($sid <= 0) return;

// Buscar datos (un registro por solicitud)
$st = $pdo->prepare("
  SELECT ID_AUDITORIA_SG,
         TIPO_AUDITORIA,
         TIPO_SISTEMA,
         FECHA_INICIO,
         FECHA_FIN,
         LUGAR
  FROM dbo.DOCENTE_AUDITORIA_SG
  WHERE ID_SOLICITUD = :sid
");
$st->execute([':sid' => $sid]);
$A = $st->fetch(PDO::FETCH_ASSOC);

$idAud      = (int)($A['ID_AUDITORIA_SG'] ?? 0);
$tipoAud    = $A['TIPO_AUDITORIA'] ?? '';
$tipoSist   = $A['TIPO_SISTEMA']   ?? '';
$lugar      = $A['LUGAR']          ?? '';
$fechaIni   = '';
$fechaFin   = '';

if (!empty($A['FECHA_INICIO'])) {
  $fechaIni = substr((string)$A['FECHA_INICIO'], 0, 10);
}
if (!empty($A['FECHA_FIN'])) {
  $fechaFin = substr((string)$A['FECHA_FIN'], 0, 10);
}
?>
<section class="card" style="margin-top:1rem;padding:1rem 1.25rem">
  <h3 style="margin:0 0 .75rem">Auditorías de sistemas de gestión (PASG)</h3>

  <form method="post"
        action="/siged/public/index.php?action=pasg_guardar"
        style="display:grid;gap:.75rem">
    <input type="hidden" name="id" value="<?= $sid ?>">
    <input type="hidden" name="id_auditoria" value="<?= $idAud ?>">

    <div style="display:grid;grid-template-columns:1fr 2fr;gap:.75rem">
      <label>Tipo de auditoría
        <select name="tipo_auditoria" required>
          <option value="">Seleccione...</option>
          <option value="interna" <?= $tipoAud==='interna' ? 'selected' : '' ?>>Interna</option>
          <option value="externa" <?= $tipoAud==='externa' ? 'selected' : '' ?>>Externa</option>
        </select>
      </label>

      <label>Sistema de gestión
        <input type="text"
               name="tipo_sistema"
               required
               value="<?= htmlspecialchars($tipoSist) ?>"
               placeholder="p.ej. SGC, SGA, SGIG, Igualdad Laboral">
      </label>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
      <label>Fecha de inicio
        <input type="date"
               name="fecha_inicio"
               required
               value="<?= htmlspecialchars($fechaIni) ?>">
      </label>
      <label>Fecha de término
        <input type="date"
               name="fecha_fin"
               required
               value="<?= htmlspecialchars($fechaFin) ?>">
      </label>
    </div>

    <label>Lugar / Departamento
      <input type="text"
             name="lugar"
             required
             value="<?= htmlspecialchars($lugar) ?>"
             placeholder="p.ej. Departamento de Recursos Humanos, TecNM Campus Culiacán">
    </label>

    <div style="display:flex;gap:.5rem;margin-top:.5rem">
      <button type="submit" class="btn">Guardar datos</button>
      <a class="btn" href="/siged/public/index.php?action=jefe_ver&id=<?= $sid ?>">Volver</a>
    </div>
  </form>

  <?php if (isset($_GET['pasg_saved'])): ?>
    <div class="alert" style="margin-top:.5rem;background:#ecfdf3;border:1px solid #bbf7d0;color:#166534;padding:.5rem;border-radius:.5rem">
      Información de auditoría guardada correctamente.
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['pasg_err']) && $_GET['pasg_err']==='campos'): ?>
    <div class="alert" style="margin-top:.5rem;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:.5rem;border-radius:.5rem">
      Por favor completa todos los campos de la auditoría.
    </div>
  <?php endif; ?>
</section>
