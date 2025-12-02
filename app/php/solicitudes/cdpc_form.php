<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';

// Montado por cdpc_mount.php
if (!isset($sol) || strtoupper($sol['tipo'] ?? '') !== 'CDPC') {
  return;
}

$sid = (int)($sol['id'] ?? 0);
if ($sid <= 0) return;
?>
<section class="card" style="margin-top:1rem;padding:1rem 1.25rem">
  <h3 style="margin:0 0 .75rem">Comisión Diplomado en Pensamiento Crítico (CDPC)</h3>

  <p style="margin:0 0 .75rem;font-size:.9rem;color:#4b5563;text-align:justify">
    Este documento es un oficio de comisión donde se indica que el(la) docente
    ha sido comisionado(a) como instructor(a)/facilitador(a) del Diplomado en
    Pensamiento Crítico para la Educación Tecnológica del TecNM, conforme al
    numeral 1.2.2.3 del programa EDD.
  </p>

  <p style="margin:0 0 .75rem;font-size:.9rem;color:#4b5563;text-align:justify">
    La información del documento se genera automáticamente a partir de los datos
    del docente y de la solicitud. No se requiere capturar información adicional
    para este tipo de evidencia.
  </p>

  <div style="display:flex;gap:.5rem;margin-top:.5rem">
    <a class="btn" href="/siged/public/index.php?action=jefe_ver&id=<?= $sid ?>">Volver</a>
  </div>

  <?php if (isset($_GET['cdpc_saved'])): ?>
    <div class="alert" style="margin-top:.5rem;background:#ecfdf3;border:1px solid #bbf7d0;color:#166534;padding:.5rem;border-radius:.5rem">
      Información registrada correctamente.
    </div>
  <?php endif; ?>
</section>
