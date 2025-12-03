<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';

// Montado por cmel_mount.php
if (!isset($sol) || strtoupper($sol['tipo'] ?? '') !== 'CMEL') {
  return;
}

$pdo = DB::conn();
$sid = (int)($sol['id'] ?? 0);
if ($sid <= 0) return;

// Traer último registro CMEL si existe
$st = $pdo->prepare("
  SELECT TOP 1
         ID_CONST,
         PROGRAMA,
         NOMBRE_MODULOS,
         FECHA_EMISION
  FROM dbo.DOC_CONST_MOD_ESP_LIC
  WHERE ID_SOLICITUD = :sid
  ORDER BY ID_CONST DESC
");
$st->execute([':sid' => $sid]);
$C = $st->fetch(PDO::FETCH_ASSOC);

$idConst      = (int)($C['ID_CONST'] ?? 0);
$programa     = (string)($C['PROGRAMA'] ?? '');
$modulosRaw   = (string)($C['NOMBRE_MODULOS'] ?? '');
$fechaRaw     = (string)($C['FECHA_EMISION'] ?? '');

// Fecha para input date
$fechaEmision = $fechaRaw ? date('Y-m-d', strtotime($fechaRaw)) : date('Y-m-d');

// Estados en los que permites edición (ajusta si quieres más estricto)
$estadoSol        = (string)($sol['estado'] ?? '');
$estadosEditables = ['ENVIADA', 'APROBADA'];
$btnDisabled      = in_array($estadoSol, $estadosEditables, true) ? '' : 'disabled';

?>
<section class="card" style="margin-top:1rem;padding:1rem 1.25rem">
  <h3 style="margin:0 0 .75rem">
    Constancia módulos de especialidad (CMEL)
  </h3>

  <form method="post"
        action="/siged/public/index.php?action=cmel_guardar"
        style="display:grid;gap:.75rem">
    <input type="hidden" name="id" value="<?= $sid ?>">
    <input type="hidden" name="id_const" value="<?= $idConst ?>">

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:.75rem">
      <label>Programa de licenciatura
        <input type="text"
               name="programa"
               required
               value="<?= htmlspecialchars($programa, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
               placeholder='p.ej. "Ingeniería en Sistemas Computacionales"'>
      </label>

      <label>Fecha de emisión
        <input type="date"
               name="fecha_emision"
               value="<?= htmlspecialchars($fechaEmision, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
      </label>
    </div>

    <label>Módulos de especialidad
      <textarea name="nombre_modulos"
                rows="4"
                required
                placeholder='Ejemplo: "Módulos de especialidad en Desarrollo de Software: Desarrollo Web, Arquitecturas Empresariales, Integración de Sistemas"'
                style="width:100%;resize:vertical"><?= htmlspecialchars($modulosRaw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
    </label>

    <div style="margin-top:.5rem;display:flex;gap:.5rem;align-items:center">
      <button type="submit"
              class="btn primary"
              <?= $btnDisabled ?>>
        Guardar datos
      </button>

      <?php if ($btnDisabled): ?>
        <span style="font-size:12px;color:#9ca3af">
          Solo editable cuando la solicitud está en estado
          <strong>ENVIADA</strong> o <strong>APROBADA</strong>.
        </span>
      <?php endif; ?>
    </div>
  </form>

  <?php if (isset($_GET['cmel_saved'])): ?>
    <div class="alert" style="margin-top:.5rem;background:#ecfdf3;border:1px solid #bbf7d0;color:#166534;padding:.5rem;border-radius:.5rem">
      Datos de la constancia guardados correctamente.
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['cmel_err']) && $_GET['cmel_err']==='campos'): ?>
    <div class="alert" style="margin-top:.5rem;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:.5rem;border-radius:.5rem">
      Por favor completa el programa, los módulos de especialidad y una fecha de emisión válida.
    </div>
  <?php endif; ?>
</section>
