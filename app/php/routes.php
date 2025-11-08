<?php
declare(strict_types=1);

function route(string $action): void {
  switch ($action) {
    case 'login':   require __DIR__ . '/auth/login.php'; break;
    case 'logout':  require __DIR__ . '/auth/logout.php'; break;

    // ===== Docente =====
    case 'home_docente':
      require __DIR__ . '/utils/roles.php';
      requireRole(['DOCENTE']);
      require __DIR__ . '/docente/home_docente.php';  // << usa la versión nueva
      break;

    case 'doc_home_data':
      require __DIR__ . '/docente/home_data.php';     // << endpoint JSON
      break;

    // ===== Jefe =====
    case 'home_jefe':
      require __DIR__ . '/utils/roles.php';
      requireRole(['JEFE_DEPARTAMENTO']);
      require __DIR__ . '/usuarios/home_jefe.php';
      break;

    // ===== Convocatoria (modal) =====
    case 'conv_get':  require __DIR__ . '/convocatoria/get.php';  break;
    case 'conv_ack':  require __DIR__ . '/convocatoria/ack.php';  break;
    case 'conv_ping':
      header('Content-Type: text/plain; charset=UTF-8'); echo "OK / routes.php alcanzado\n"; break;

    // ===== Solicitudes =====
    case 'sol_nueva':     require __DIR__ . '/utils/roles.php'; requireRole(['DOCENTE']); require __DIR__ . '/solicitudes/nueva.php'; break;
    case 'sol_guardar':   require __DIR__ . '/utils/roles.php'; requireRole(['DOCENTE']); require __DIR__ . '/solicitudes/guardar.php'; break;
    case 'sol_editar':    require __DIR__ . '/utils/roles.php'; requireRole(['DOCENTE']); require __DIR__ . '/solicitudes/editar.php'; break;
    case 'sol_subir':     require __DIR__ . '/utils/roles.php'; requireRole(['DOCENTE']); require __DIR__ . '/solicitudes/subir.php'; break;
    case 'sol_enviar':    require __DIR__ . '/utils/roles.php'; requireRole(['DOCENTE']); require __DIR__ . '/solicitudes/enviar.php'; break;
    case 'sol_mis':       require __DIR__ . '/utils/roles.php'; requireRole(['DOCENTE']); require __DIR__ . '/solicitudes/mis.php'; break;
    case 'jefe_bandeja':  require __DIR__ . '/utils/roles.php'; requireRole(['JEFE_DEPARTAMENTO']); require __DIR__ . '/solicitudes/jefe_bandeja.php'; break;
    case 'jefe_ver':      require __DIR__ . '/utils/roles.php'; requireRole(['JEFE_DEPARTAMENTO']); require __DIR__ . '/solicitudes/jefe_ver.php'; break;
    case 'jefe_decidir':  require __DIR__ . '/utils/roles.php'; requireRole(['JEFE_DEPARTAMENTO']); require __DIR__ . '/solicitudes/jefe_decidir.php'; break;

    // ===== PDF demo =====
    case 'pdf_demo': require __DIR__ . '/utils/roles.php'; requireLogin(); require __DIR__ . '/../../pdf/plantillas/demo_tcpdf.php'; break;

    default:
      http_response_code(404);
      echo "<h1>404</h1><p>Ruta no encontrada.</p>";
  }
}
