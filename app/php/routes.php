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

// === Firma del Docente ===
  case 'doc_firma':
  require __DIR__ . '/utils/roles.php';
  requireRole(['DOCENTE']);
  require __DIR__ . '/docente/firma.php';
  break;

  case 'doc_firma_guardar':
  require __DIR__ . '/utils/roles.php';
  requireRole(['DOCENTE']);
  require __DIR__ . '/docente/doc_firma_guardar.php';
  break;

      case 'doc_hist_data': require __DIR__ . '/docente/historico_data.php'; break;
      case 'doc_hist':      require __DIR__ . '/docente/historico.php';      break;

    case 'doc_home_data':
      require __DIR__ . '/docente/home_data.php';     // << endpoint JSON
      break;

      case 'doc_pdf':
        require __DIR__ . '/utils/roles.php';
        requireRole(['DOCENTE']);
        require __DIR__ . '/docente/pdf_generar.php';
        break;

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
    case 'ci_guardar':  require __DIR__ . '/utils/roles.php'; requireRole(['JEFE_DEPARTAMENTO']); require __DIR__ . '/solicitudes/ci_guardar.php'; break;
    case 'doc_pdf': require __DIR__ . '/docente/pdf_generar.php'; break;

    // DOCENTE: solicitar corrección (solo si ESTADO=APROBADA)
    case 'sol_corr_new':     require __DIR__ . '/solicitudes/corr_new.php'; break;
    case 'sol_corr_guardar': require __DIR__ . '/solicitudes/corr_guardar.php'; break;


       // ===== PDF demo =====
         case 'pdf_demo': require __DIR__ . '/utils/roles.php'; requireLogin(); require __DIR__ . '/../../pdf/plantillas/demo_tcpdf.php'; break;
      
        // ===== Convocatoria (modal) =====
        case 'conv_get':  require __DIR__ . '/convocatoria/get.php';  break;
        case 'conv_ack':  require __DIR__ . '/convocatoria/ack.php';  break;
        case 'conv_ping':
          header('Content-Type: text/plain; charset=UTF-8'); echo "OK / routes.php alcanzado\n"; break;
    

      // DOCENTE · Tickets
    case 'tk_list':     require __DIR__ . '/utils/roles.php'; requireRole(['DOCENTE']); require __DIR__ . '/tickets/docente_list.php'; break;
    case 'tk_data':     require __DIR__ . '/utils/roles.php'; requireRole(['DOCENTE']); require __DIR__ . '/tickets/docente_data.php'; break;
    case 'tk_crear':    require __DIR__ . '/utils/roles.php'; requireRole(['DOCENTE']); require __DIR__ . '/tickets/crear.php'; break;
    case 'tk_guardar':  require __DIR__ . '/utils/roles.php'; requireRole(['DOCENTE']); require __DIR__ . '/tickets/guardar.php'; break;
    case 'tk_ver':      require __DIR__ . '/utils/roles.php'; requireRole(['DOCENTE']); require __DIR__ . '/tickets/ver.php'; break;
    case 'tk_comentar': require __DIR__ . '/utils/roles.php'; requireRole(['DOCENTE']); require __DIR__ . '/tickets/comentar.php'; break;
    case 'tk_resp_data': require __DIR__ . '/utils/roles.php'; requireRole(['DOCENTE']); require __DIR__ . '/tickets/resp_data.php'; break;

    // ===== Jefe =====
    case 'jefe_home_data': 
      require __DIR__ . '/utils/roles.php'; 
      requireRole(['JEFE_DEPARTAMENTO']); 
      require __DIR__ . '/usuarios/jefe_home_data.php'; 
      break;


    


      // --- Firma del Jefe ---
  case 'jefe_firma_guardar':
  require __DIR__ . '/utils/roles.php';
  requireRole(['JEFE_DEPARTAMENTO']);
  require __DIR__ . '/usuarios/jefe_firma_guardar.php';
  break;

    case 'home_jefe':
      require __DIR__ . '/utils/roles.php';
      requireRole(['JEFE_DEPARTAMENTO']);
      require __DIR__ . '/usuarios/home_jefe.php';
      break;

  // JEFE: atender corrección
  case 'corr_editar':  require __DIR__ . '/solicitudes/corr_editar.php'; break;
  case 'corr_aplicar': require __DIR__ . '/solicitudes/corr_aplicar.php'; break;
  case 'dep_guardar': require __DIR__ . '/solicitudes/dep_guardar.php'; break;

      
      // JEFE · Tickets
    case 'tkj_list':     require __DIR__ . '/utils/roles.php'; requireRole(['JEFE_DEPARTAMENTO']); require __DIR__ . '/tickets_jefe/list.php'; break;
    case 'tkj_data':     require __DIR__ . '/utils/roles.php'; requireRole(['JEFE_DEPARTAMENTO']); require __DIR__ . '/tickets_jefe/data.php'; break;
    case 'tkj_ver':      require __DIR__ . '/utils/roles.php'; requireRole(['JEFE_DEPARTAMENTO']); require __DIR__ . '/tickets_jefe/ver.php'; break;
    case 'tkj_comentar': require __DIR__ . '/utils/roles.php'; requireRole(['JEFE_DEPARTAMENTO']); require __DIR__ . '/tickets_jefe/comentar.php'; break;
    case 'tkj_estado':   require __DIR__ . '/utils/roles.php'; requireRole(['JEFE_DEPARTAMENTO']); require __DIR__ . '/tickets_jefe/estado.php'; break;
    
   


    default:
      http_response_code(404);
      echo "<h1>404</h1><p>Ruta no encontrada.</p>";
  }
}
