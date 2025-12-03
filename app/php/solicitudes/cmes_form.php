<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';

// Montado por cmes_mount.php
if (!isset($sol) || strtoupper($sol['tipo'] ?? '') !== 'CMES') {
  return;
}

$pdo = DB::conn();
$sid = (int)($sol['id'] ?? 0);
if ($sid <= 0) return;

// Traer último registro (si existe)
$st = $pdo->prepare("
  SELECT TOP 1
         ID_COMISION,
         PROGRAMA,
         FECHA_INICIO,
         FECHA_FIN
  FROM dbo.DOC_COMISION_MOD_ESP
  WHERE ID_SOLICITUD = :sid
  ORDER BY ID_COMISION DESC
");
$st->execute([':sid' => $sid]);
$C = $st->fetch(PDO::FETCH_ASSOC);

$idCom   = (int)($C['ID_COMISION'] ?? 0);
$programa = (string)($C['PROGRAMA'] ?? '');
$fiRaw    = (string)($C['FECHA_INICIO'] ?? '');
$ffRaw    = (string)($C['FECHA_FIN'] ?? '');

// Fechas en formato para input date (Y-m-d)
$fi = $fiRaw ? date('Y-m-d', strtotime($fiRaw)) : '';
$ff = $ffRaw ? date('Y-m-d', strtotime($ffRaw)) : '';

// Estados en los que permites edición
$estadoSol         = (string)($sol['estado'] ?? '');
$estadosEditables  = ['ENVIADA', 'APROBADA'];
$btnDisabled       = in_array($estadoSol, $estadosEditables, true) ? '' : 'disabled';
?>
<section class="card" style="margin-top:1rem;padding:1rem 1.25rem">
  <h3 style="margin:0 0 .75rem">
    Comisión módulos de especialidad (CMES)
  </h3>

  <form method="post"
        action="/siged/public/index.php?action=cmes_guardar"
        style="display:grid;gap:.75rem">
    <input type="hidden" name="id" value="<?= $sid ?>">
    <input type="hidden" name="id_comision" value="<?= $idCom ?>">

    <div style="display:grid;grid-template-columns:1.5fr 1fr 1fr;gap:.75rem">
      <label>Programa educativo
        <input type="text"
               name="programa"
               required
               value="<?= htmlspecialchars($programa, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
               placeholder='p.ej. "Ingeniería en Sistemas Computacionales"'>
      </label>

      <label>Fecha inicio del periodo
        <input type="date"
               name="fecha_inicio"
               required
               value="<?= htmlspecialchars($fi, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
      </label>

      <label>Fecha fin del periodo
        <input type="date"
               name="fecha_fin"
               required
               value="<?= htmlspecialchars($ff, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
      </label>
    </div>

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

  <?php if (isset($_GET['cmes_saved'])): ?>
    <div class="alert" style="margin-top:.5rem;background:#ecfdf3;border:1px solid #bbf7d0;color:#166534;padding:.5rem;border-radius:.5rem">
      Datos de comisión guardados correctamente.
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['cmes_err']) && $_GET['cmes_err']==='campos'): ?>
    <div class="alert" style="margin-top:.5rem;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:.5rem;border-radius:.5rem">
      Por favor completa todos los campos requeridos y verifica que las fechas sean válidas.
    </div>
  <?php endif; ?>
</section>
