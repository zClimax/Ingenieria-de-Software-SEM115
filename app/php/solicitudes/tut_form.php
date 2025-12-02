<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';


if (!isset($sol) || strtoupper($sol['tipo'] ?? '') !== 'TUT') {
  return;
}

$pdo = DB::conn();
$sid = (int)($sol['id'] ?? 0);
if ($sid <= 0) return;

// Traer último registro capturado (si existe)
$st = $pdo->prepare("
  SELECT TOP 1 TUT_EJ_2024,
               TUT_AD_2024,
               LUGAR,
               FECHA_EMISION
  FROM dbo.DOC_SE_TUTORADOS
  WHERE ID_SOLICITUD = :sid
  ORDER BY ID_TUT DESC
");
$st->execute([':sid' => $sid]);
$prev = $st->fetch(PDO::FETCH_ASSOC) ?: [
  'TUT_EJ_2024'   => 0,
  'TUT_AD_2024'   => 0,
  'LUGAR'         => 'Culiacán, Sinaloa',
  'FECHA_EMISION' => date('Y-m-d'),
];

$tutEJ = (int)($prev['TUT_EJ_2024'] ?? 0);
$tutAD = (int)($prev['TUT_AD_2024'] ?? 0);
$lugar = (string)($prev['LUGAR'] ?? 'Culiacán, Sinaloa');
$fecha = (string)($prev['FECHA_EMISION'] ?? date('Y-m-d'));

// Controlar habilitado del botón según estado de la solicitud
$estadoSol = (string)($sol['estado'] ?? '');
// Estados en los que SÍ se permite capturar / modificar datos de tutorados
$estadosEditables = ['ENVIADA', 'APROBADA'];

$btnDisabled = in_array($estadoSol, $estadosEditables, true) ? '' : 'disabled';
?>
<section class="card" style="margin-bottom:12px;padding:1rem 1.25rem">
  <h3 style="margin-top:0">Datos para la Constancia de Tutorados (TUT)</h3>

  <form method="post" action="/siged/public/index.php?action=tut_guardar">
    <input type="hidden" name="id" value="<?= (int)$sid ?>">

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
      <label>
        Tutorados Ene–Jun 2024
        <input type="number"
               name="tut_ej"
               min="0"
               step="1"
               value="<?= $tutEJ ?>"
               required>
      </label>

      <label>
        Tutorados Ago–Dic 2024
        <input type="number"
               name="tut_ad"
               min="0"
               step="1"
               value="<?= $tutAD ?>"
               required>
      </label>
    </div>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:10px;margin-top:.5rem">
      <label>
        Lugar
        <input type="text"
               name="lugar"
               value="<?= htmlspecialchars($lugar, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
      </label>

      <label>
        Fecha de emisión
        <input type="date"
               name="fecha"
               value="<?= htmlspecialchars(substr($fecha,0,10), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
      </label>
    </div>

    <p style="font-size:12px;color:#555;margin:.5rem 0 0">
      * Puntaje de referencia: 3 puntos por alumno, con tope de 45 puntos.
      El cálculo se realizará de forma interna.
    </p>

    <div style="margin-top:.75rem">
    <button type="submit"
        class="btn primary"
        <?= $btnDisabled ?>>
  Guardar datos
</button>

<?php if ($btnDisabled): ?>
  <span style="font-size:12px;color:#9ca3af;margin-left:.5rem">
    Solo editable cuando la solicitud está en estado
    <strong>ENVIADA</strong> o <strong>APROBADA</strong>.
  </span>
<?php endif; ?>
    </div>
  </form>
</section>
