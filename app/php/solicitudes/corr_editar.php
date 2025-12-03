<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';

requireRole(['JEFE_DEPARTAMENTO']);
Session::start();

$pdo  = DB::conn();
$user = Session::user();

$sid = (int)($_GET['id'] ?? 0);
if ($sid <= 0) { http_response_code(400); exit('ID inválido'); }

// ===== Fallback seguro del departamento del Jefe
$depJefe = (int)($user['id_departamento'] ?? 0);
if ($depJefe <= 0) {
  $qDep = $pdo->prepare("SELECT ID_DEPARTAMENTO FROM dbo.USUARIOS WHERE ID_USUARIO=:u");
  $qDep->execute([':u' => (int)$user['id']]);
  $depJefe = (int)($qDep->fetchColumn() ?: 0);
  if ($depJefe <= 0) { http_response_code(403); exit('No se pudo resolver tu departamento.'); }
}

// ===== Traer solicitud + corrección ABIERTA/EN_EDICION para este depto
$sql = $pdo->prepare("
  SELECT s.ID_SOLICITUD, s.TIPO_DOCUMENTO, s.ESTADO, s.ID_DOCENTE, s.ID_DEPARTAMENTO_APROBADOR,
         d.NOMBRE_DOCENTE, d.APELLIDO_PATERNO_DOCENTE, d.APELLIDO_MATERNO_DOCENTE,
         c.ID_CORRECCION, c.ESTATUS AS EST_CORR, c.MOTIVO, c.CREATED_AT
  FROM dbo.SOLICITUD_DOCUMENTO s
  JOIN dbo.DOC_CORRECCION c
       ON c.ID_SOLICITUD = s.ID_SOLICITUD
      AND c.ESTATUS IN ('ABIERTA','EN_EDICION')
  JOIN dbo.DOCENTE d
       ON d.ID_DOCENTE = s.ID_DOCENTE
  WHERE s.ID_SOLICITUD = :id
    AND c.ID_DEP_DESTINO = :dep
");
$sql->execute([':id' => $sid, ':dep' => $depJefe]);
$row = $sql->fetch(PDO::FETCH_ASSOC);

if (!$row) {
  http_response_code(404);
  exit('No hay una corrección abierta para tu departamento en esta solicitud.');
}

if ((int)$row['ID_DEPARTAMENTO_APROBADOR'] !== $depJefe) {
  http_response_code(403);
  exit('No autorizado: la solicitud pertenece a otro departamento.');
}

// ===== Normalizaciones y etiquetas de apoyo
$tipo         = strtoupper((string)$row['TIPO_DOCUMENTO']);
$estado       = (string)$row['ESTADO'];
$docenteNom   = trim(($row['NOMBRE_DOCENTE'] ?? '') . ' ' . ($row['APELLIDO_PATERNO_DOCENTE'] ?? '') . ' ' . ($row['APELLIDO_MATERNO_DOCENTE'] ?? ''));
$motivo       = (string)$row['MOTIVO'];
$idCorreccion = (int)$row['ID_CORRECCION'];

// ===== Estructura $sol para formularios específicos por tipo (ej. ACI)
$sol = [
  'id'            => (int)$row['ID_SOLICITUD'],
  'tipo'          => $tipo,
  'estado'        => $estado,
  'id_docente'    => (int)$row['ID_DOCENTE'],
  'dep_aprobador' => (int)$row['ID_DEPARTAMENTO_APROBADOR'],
];
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Corrección · Solicitud #<?= (int)$sol['id'] ?> · <?= htmlspecialchars($tipo) ?></title>
  <link rel="stylesheet" href="/siged/app/css/styles.css">
</head>
<body class="layout">
  <main class="card" style="max-width:960px;margin:2rem auto">
    <h1 style="margin-top:0">Corrección · Solicitud #<?= (int)$sol['id'] ?> · <?= htmlspecialchars($tipo) ?></h1>
    <p><strong>Docente:</strong> <?= htmlspecialchars($docenteNom) ?></p>
    <div class="alert" style="background:#FEF9C3;color:#854D0E">
      <strong>Motivo del docente:</strong> <?= nl2br(htmlspecialchars($motivo)) ?>
    </div>

    <?php
    // ====== FORMULARIOS ESPECÍFICOS POR TIPO ======
    if ($tipo === 'CINF') {

      require __DIR__ . '/ci_form.php';
  
  } elseif ($tipo === 'RED') {
  
      require __DIR__ . '/dep_form.php';
  
  } elseif ($tipo === 'TUT') {
  
      require __DIR__ . '/tut_mount.php';
  
  }elseif ($tipo === 'CAE') {
  
      require __DIR__ . '/cae_form.php';
  
  } elseif ($tipo === 'CCA') {
  
      require __DIR__ . '/cca_form.php';
  
  } elseif ($tipo === 'CCE') {
  
      require __DIR__ . '/cce_form.php';
  
  } elseif ($tipo === 'CCID') {
  
      require __DIR__ . '/ccid_form.php';

   } elseif ($tipo === 'CCO') {
  
      require __DIR__ . '/cco_form.php';
  
  } elseif ($tipo === 'CCUI') {
  
      require __DIR__ . '/ccui_form.php';  

  } elseif ($tipo === 'CDEI') {
  
      require __DIR__ . '/cdei_form.php';  
  
  } elseif ($tipo === 'CDPC') {
  
      require __DIR__ . '/cdpc_form.php';
      
  } elseif ($tipo === 'CDPE') {
  
      require __DIR__ . '/cdpe_form.php';

  } elseif ($tipo === 'CDRE') {
  
      require __DIR__ . '/cdre_form.php';

   } elseif ($tipo === 'CEPA') {
  
      require __DIR__ . '/cepa_form.php';

   } elseif ($tipo === 'CHA') {
  
      require __DIR__ . '/cha_form.php';

   } elseif ($tipo === 'CI') {
  
      require __DIR__ . '/ci_form.php';

   } elseif ($tipo === 'CIPC') {
  
      require __DIR__ . '/cipc_form.php';

   } elseif ($tipo === 'CJEA') {
  
      require __DIR__ . '/cjea_form.php';

   } elseif ($tipo === 'CLFG') {
  
      require __DIR__ . '/clfg_form.php';

   } elseif ($tipo === 'CMDI') {
  
      require __DIR__ . '/cmdi_form.php';

   } elseif ($tipo === 'CMP') {
  
      require __DIR__ . '/cmp_form.php';

   } elseif ($tipo === 'CPFT') {
  
      require __DIR__ . '/cpft_form.php';

   } elseif ($tipo === 'CPI') {
  
      require __DIR__ . '/cpi_form.php';


   } elseif ($tipo === 'CPP') {
  
      require __DIR__ . '/cpp_form.php';

   } elseif ($tipo === 'CSE') {
  
      require __DIR__ . '/cse_form.php';

   } elseif ($tipo === 'CSEM') {
  
      require __DIR__ . '/csem_form.php';

   } elseif ($tipo === 'CSEP') {
  
      require __DIR__ . '/csep_form.php';

   } elseif ($tipo === 'CST') {
  
      require __DIR__ . '/cst_form.php';

   } elseif ($tipo === 'LAD') {
  
      require __DIR__ . '/lad_form.php';

   }elseif ($tipo === 'CPPL') {
  
      require __DIR__ . '/cppl_form.php';

   }elseif ($tipo === 'PASG') {
  
      require __DIR__ . '/pasg_form.php';

   }elseif ($tipo === 'CPPT') {
  
         require __DIR__ . '/cppt_form.php';
  }elseif ($tipo === 'CMES') {
  
   require __DIR__ . '/cmes_form.php';
 }elseif ($tipo === 'ORME') {
  
      require __DIR__ . '/orme_form.php';
  
  }elseif ($tipo === 'CMEL') {
  
      require __DIR__ . '/cmel_mount.php';
  
  }else {
  
      echo '<div class="card" style="padding:1rem;margin:.75rem 0">
              <h3 style="margin:0 0 .5rem">Campos específicos</h3>
              <p><em>No hay formulario estructurado para este tipo de documento.</em></p>
            </div>';
  }
  
    ?>

    <form method="post" action="/siged/public/index.php?action=corr_aplicar" style="margin-top:1rem">
      <input type="hidden" name="id" value="<?= (int)$sol['id'] ?>">
      <label>Notas del jefe (opcional)
        <textarea name="notas" rows="2" style="width:100%"></textarea>
      </label>
      <div style="margin-top:.75rem">
        <button>Aplicar corrección y versionar PDF</button>
        <a href="/siged/public/index.php?action=jefe_bandeja" style="margin-left:8px">Cancelar</a>
      </div>
    </form>

    <p style="margin-top:1rem"><a href="/siged/public/index.php?action=jefe_bandeja">← Volver a Bandeja</a></p>
  </main>
</body>
</html>
