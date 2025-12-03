<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';

// Montado por cppt_mount.php
if (!isset($sol) || strtoupper($sol['tipo'] ?? '') !== 'CPPT') {
  return;
}

$pdo = DB::conn();
$sid = (int)($sol['id'] ?? 0);
if ($sid <= 0) return;

// Último registro, si existe
$st = $pdo->prepare("
  SELECT TOP 1 ID_PT,
               TIPO_PART,
               NOMBRE_PROG
  FROM dbo.DOC_PLANES_PROG_TECNM
  WHERE ID_SOLICITUD = :sid
  ORDER BY ID_PT DESC
");
$st->execute([':sid' => $sid]);
$C = $st->fetch(PDO::FETCH_ASSOC);

$idPT    = (int)($C['ID_PT'] ?? 0);
$tipoPart = (string)($C['TIPO_PART']   ?? '');
$nomProg  = (string)($C['NOMBRE_PROG'] ?? '');

// Estados en los que se permite editar
$estadoSol        = (string)($sol['estado'] ?? '');
$estadosEditables = ['ENVIADA', 'APROBADA'];
$btnDisabled      = in_array($estadoSol, $estadosEditables, true) ? '' : 'disabled';
?>
<section class="card" style="margin-top:1rem;padding:1rem 1.25rem">
  <h3 style="margin:0 0 .75rem">
    Planes y programas TecNM (CPPT)
  </h3>

  <form method="post"
        action="/siged/public/index.php?action=cppt_guardar"
        style="display:grid;gap:.75rem">
    <input type="hidden" name="id" value="<?= $sid ?>">
    <input type="hidden" name="id_pt" value="<?= $idPT ?>">

    <div style="display:grid;grid-template-columns:1fr 2fr;gap:.75rem">
      <label>Tipo de participación
        <select name="tipo_part" required>
          <option value="">Seleccione...</option>
          <option value="elaboración"
            <?= $tipoPart==='elaboración' ? 'selected' : '' ?>>Elaboración</option>
          <option value="actualización"
            <?= $tipoPart==='actualización' ? 'selected' : '' ?>>Actualización</option>
        </select>
      </label>

      <label>Programa educativo
        <input type="text"
               name="nombre_prog"
               required
               value="<?= htmlspecialchars($nomProg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
               placeholder='p.ej. "Ingeniería en Sistemas Computacionales"'>
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

  <?php if (isset($_GET['cppt_saved'])): ?>
    <div class="alert" style="margin-top:.5rem;background:#ecfdf3;border:1px solid #bbf7d0;color:#166534;padding:.5rem;border-radius:.5rem">
      Información de participación nacional guardada correctamente.
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['cppt_err']) && $_GET['cppt_err']==='campos'): ?>
    <div class="alert" style="margin-top:.5rem;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:.5rem;border-radius:.5rem">
      Por favor completa todos los campos requeridos.
    </div>
  <?php endif; ?>
</section>
