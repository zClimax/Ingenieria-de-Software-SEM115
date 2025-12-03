<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';

// Montado por orme_mount.php
if (!isset($sol) || strtoupper($sol['tipo'] ?? '') !== 'ORME') {
  return;
}

$pdo = DB::conn();
$sid = (int)($sol['id'] ?? 0);
if ($sid <= 0) return;

// Traer último registro (si existe)
$st = $pdo->prepare("
  SELECT TOP 1
         ID_OR,
         PROGRAMA,
         LISTA_MODULOS,
         FECHA_EMISION
  FROM dbo.DOC_OFICIO_REG_MOD_ESP
  WHERE ID_SOLICITUD = :sid
  ORDER BY ID_OR DESC
");
$st->execute([':sid' => $sid]);
$C = $st->fetch(PDO::FETCH_ASSOC);

$idOr        = (int)($C['ID_OR'] ?? 0);
$programa    = (string)($C['PROGRAMA'] ?? '');
$listaRaw    = (string)($C['LISTA_MODULOS'] ?? '');
$fechaRaw    = (string)($C['FECHA_EMISION'] ?? '');

// Fecha en formato para input date
$fechaEmision = $fechaRaw ? date('Y-m-d', strtotime($fechaRaw)) : date('Y-m-d');

// Estados en los que permites edición
$estadoSol        = (string)($sol['estado'] ?? '');
$estadosEditables = ['ENVIADA', 'APROBADA'];
$btnDisabled      = in_array($estadoSol, $estadosEditables, true) ? '' : 'disabled';

?>
<section class="card" style="margin-top:1rem;padding:1rem 1.25rem">
  <h3 style="margin:0 0 .75rem">
    Oficio registro módulos de especialidad (ORME)
  </h3>

  <form method="post"
        action="/siged/public/index.php?action=orme_guardar"
        style="display:grid;gap:.75rem">
    <input type="hidden" name="id" value="<?= $sid ?>">
    <input type="hidden" name="id_or" value="<?= $idOr ?>">

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:.75rem">
      <label>Programa educativo
        <input type="text"
               name="programa"
               required
               value="<?= htmlspecialchars($programa, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
               placeholder='p.ej. "Ingeniería en Sistemas Computacionales"'>
      </label>

      <label>Fecha de emisión del oficio
        <input type="date"
               name="fecha_emision"
               value="<?= htmlspecialchars($fechaEmision, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
      </label>
    </div>

    <label>Lista de módulos de especialidad
      <textarea name="lista_modulos"
                rows="4"
                required
                placeholder="Escribe uno por línea, por ejemplo:
Módulo I. Fundamentos de X
Módulo II. Aplicaciones de Y
Módulo III. Integración de Z"
                style="width:100%;resize:vertical"><?= htmlspecialchars($listaRaw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>
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

  <?php if (isset($_GET['orme_saved'])): ?>
    <div class="alert" style="margin-top:.5rem;background:#ecfdf3;border:1px solid #bbf7d0;color:#166534;padding:.5rem;border-radius:.5rem">
      Datos del oficio guardados correctamente.
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['orme_err']) && $_GET['orme_err']==='campos'): ?>
    <div class="alert" style="margin-top:.5rem;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:.5rem;border-radius:.5rem">
      Por favor completa el programa educativo, la lista de módulos y una fecha de emisión válida.
    </div>
  <?php endif; ?>
</section>
