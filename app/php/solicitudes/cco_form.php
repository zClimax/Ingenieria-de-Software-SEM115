<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';

// Montado por cco_mount.php
if (!isset($sol) || strtoupper($sol['tipo'] ?? '') !== 'CCO') {
  return;
}

$pdo = DB::conn();
$sid = (int)($sol['id'] ?? 0);
if ($sid <= 0) return;

// Buscar si ya existe registro
$st = $pdo->prepare("
  SELECT ID_ASESORIA_CONCURSO,
         TIPO_EVENTO,
         NOMBRE_EVENTO,
         LUGAR_EVENTO,
         FECHA_INICIO,
         FECHA_FIN
  FROM dbo.DOCENTE_ASESORIA_CONCURSO
  WHERE ID_SOLICITUD = :sid
");
$st->execute([':sid' => $sid]);
$C = $st->fetch(PDO::FETCH_ASSOC);

$idAsesoria   = (int)($C['ID_ASESORIA_CONCURSO'] ?? 0);
$tipoEvento   = $C['TIPO_EVENTO']    ?? '';
$nombreEvento = $C['NOMBRE_EVENTO']  ?? '';
$lugarEvento  = $C['LUGAR_EVENTO']   ?? '';
$fechaIni     = $C['FECHA_INICIO']   ?? '';
$fechaFin     = $C['FECHA_FIN']      ?? '';
?>
<section class="card" style="margin-top:1rem;padding:1rem 1.25rem">
  <h3 style="margin:0 0 .75rem">Asesoría en concursos / eventos académicos (CCO)</h3>

  <form method="post"
        action="/siged/public/index.php?action=cco_guardar"
        style="display:grid;gap:.75rem">
    <input type="hidden" name="id" value="<?= $sid ?>">
    <input type="hidden" name="id_asesoria" value="<?= $idAsesoria ?>">

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
      <label>Tipo de actividad
        <select name="tipo_evento" required>
          <option value="">Seleccione...</option>
          <option value="evento" <?= $tipoEvento==='evento' ? 'selected' : '' ?>>Evento académico</option>
          <option value="concurso" <?= $tipoEvento==='concurso' ? 'selected' : '' ?>>Concurso académico</option>
        </select>
      </label>

      <label>Lugar
        <input type="text"
               name="lugar_evento"
               required
               value="<?= htmlspecialchars($lugarEvento) ?>"
               placeholder="p.ej. Ciudad de México, CDMX">
      </label>
    </div>

    <label>Nombre del evento / concurso
      <input type="text"
             name="nombre_evento"
             required
             value="<?= htmlspecialchars($nombreEvento) ?>"
             placeholder="p.ej. ENECB-CEA 2024">
    </label>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem">
      <label>Fecha de inicio
        <input type="text"
               name="fecha_inicio"
               required
               value="<?= htmlspecialchars($fechaIni) ?>"
               placeholder="p.ej. 10 de junio de 2024">
      </label>

      <label>Fecha de término
        <input type="text"
               name="fecha_fin"
               required
               value="<?= htmlspecialchars($fechaFin) ?>"
               placeholder="p.ej. 14 de junio de 2024">
      </label>
    </div>

    <div style="display:flex;gap:.5rem;margin-top:.5rem">
      <button type="submit" class="btn">Guardar datos</button>
      <a class="btn" href="/siged/public/index.php?action=jefe_ver&id=<?= $sid ?>">Volver</a>
    </div>
  </form>

  <?php if (isset($_GET['cco_saved'])): ?>
    <div class="alert" style="margin-top:.5rem;background:#ecfdf3;border:1px solid #bbf7d0;color:#166534;padding:.5rem;border-radius:.5rem">
      Información de asesoría guardada correctamente.
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['cco_err']) && $_GET['cco_err']==='campos'): ?>
    <div class="alert" style="margin-top:.5rem;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:.5rem;border-radius:.5rem">
      Por favor completa todos los campos de la asesoría.
    </div>
  <?php endif; ?>
</section>
