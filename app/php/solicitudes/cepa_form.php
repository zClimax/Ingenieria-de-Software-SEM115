<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';

// Montado por cepa_mount.php
if (!isset($sol) || strtoupper($sol['tipo'] ?? '') !== 'CEPA') {
  return;
}

$pdo = DB::conn();
$sid = (int)($sol['id'] ?? 0);
if ($sid <= 0) return;

// Buscar si ya existe registro
$st = $pdo->prepare("
  SELECT ID_COMITE_EVAL,
         TIPO_COMITE,
         ORGANISMO
  FROM dbo.DOCENTE_COMITE_EVAL
  WHERE ID_SOLICITUD = :sid
");
$st->execute([':sid' => $sid]);
$C = $st->fetch(PDO::FETCH_ASSOC);

$idComite   = (int)($C['ID_COMITE_EVAL'] ?? 0);
$tipoComite = $C['TIPO_COMITE'] ?? '';
$organismo  = $C['ORGANISMO']  ?? '';
?>
<section class="card" style="margin-top:1rem;padding:1rem 1.25rem">
  <h3 style="margin:0 0 .75rem">Comité de evaluación / acreditación (CEPA)</h3>

  <form method="post"
        action="/siged/public/index.php?action=cepa_guardar"
        style="display:grid;gap:.75rem">
    <input type="hidden" name="id" value="<?= $sid ?>">
    <input type="hidden" name="id_comite" value="<?= $idComite ?>">

    <div style="display:grid;grid-template-columns:1fr 2fr;gap:.75rem">
      <label>Tipo de comité de evaluación
        <select name="tipo_comite_sel" onchange="cepa_toggle_custom(this)">
          <option value="">Seleccione...</option>
          <option value="propuestas de proyectos" <?= $tipoComite==='propuestas de proyectos' ? 'selected' : '' ?>>
            Propuestas de proyectos
          </option>
          <option value="acreditación de programas educativos" <?= $tipoComite==='acreditación de programas educativos' ? 'selected' : '' ?>>
            Acreditación de programas educativos
          </option>
          <option value="otro" <?= ($tipoComite!=='' && $tipoComite!=='propuestas de proyectos' && $tipoComite!=='acreditación de programas educativos') ? 'selected' : '' ?>>
            Otro (especificar)
          </option>
        </select>
      </label>

      <label>Organismo
        <input type="text"
               name="organismo"
               required
               value="<?= htmlspecialchars($organismo) ?>"
               placeholder="p.ej. COPAES, ABET, CONAHCYT, PRODEP">
      </label>
    </div>

    <label>Descripción del comité (si seleccionaste "Otro", especificar aquí)
      <input type="text"
             name="tipo_comite_custom"
             value="<?= htmlspecialchars($tipoComite) ?>"
             placeholder="p.ej. comité de evaluación de proyectos PRODEP">
    </label>

    <small style="color:#6b7280">
      Si eliges una opción estándar, se usará tal cual en el texto
      "comité de evaluación de &lt;tipo&gt;". Si dejas "Otro", se usará exactamente
      el texto que escribas arriba.
    </small>

    <div style="display:flex;gap:.5rem;margin-top:.5rem">
      <button type="submit" class="btn">Guardar datos</button>
      <a class="btn" href="/siged/public/index.php?action=jefe_ver&id=<?= $sid ?>">Volver</a>
    </div>
  </form>

  <?php if (isset($_GET['cepa_saved'])): ?>
    <div class="alert" style="margin-top:.5rem;background:#ecfdf3;border:1px solid #bbf7d0;color:#166534;padding:.5rem;border-radius:.5rem">
      Información del comité de evaluación guardada correctamente.
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['cepa_err']) && $_GET['cepa_err']==='campos'): ?>
    <div class="alert" style="margin-top:.5rem;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:.5rem;border-radius:.5rem">
      Por favor completa al menos el tipo de comité y el organismo.
    </div>
  <?php endif; ?>

  <script>
    function cepa_toggle_custom(sel) {
      // Sólo UX mínimo; el backend no depende de esto
      // (Puedes ampliarlo si quieres deshabilitar el campo cuando no aplique)
    }
  </script>
</section>
