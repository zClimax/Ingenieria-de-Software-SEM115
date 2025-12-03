<?php
declare(strict_types=1);

require_once __DIR__ . '/utils/session.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/utils/roles.php';

Session::start();
requireRole(['SUBDIRECTOR_ACADEMICO']); // Ajusta el nombre del rol si en tu sistema se llama distinto

$pdo  = DB::conn();
$user = (array)(Session::user() ?? []);
$uid  = (int)($user['id'] ?? 0);
if ($uid <= 0) { http_response_code(403); exit('Sesión inválida'); }

// ============================
// 1) Lista de tipos de doc que firma el subdirector
// ============================
$DOCS_SUBDIR = [
  'LAD',
  'CCO',
  'TUT',
  'CCID',
  'CAE',
  'CPI',
  'CMDI',
  'CMP',
  'CCIU',
  'CPP',
  'CLFG',
  'CIPC',
  'CCE',
  


  // agrega aquí todos los tipos reales
];

// ============================
// 2) Datos del subdirector (para firma y filtros)
// ============================
$miDep = 0;
if (!empty($user['id_departamento']) && (int)$user['id_departamento'] > 0) {
    $miDep = (int)$user['id_departamento'];
} else {
    $q = $pdo->prepare("SELECT ID_DEPARTAMENTO FROM dbo.USUARIOS WHERE ID_USUARIO=:idu");
    $q->execute([':idu' => $uid]);
    $miDep = (int)($q->fetchColumn() ?: 0);
}

$nombreCompleto = $user['nombre'] ?? ($user['NOMBRE_COMPLETO'] ?? 'Usuario');

// ============================
// 3) Firma del subdirector (preview)
// ============================
// 3.1 Obtener ruta real desde la BD
$rutaFirmaRel = '';
$stmt = $pdo->prepare("
    SELECT RUTA_FIRMA
    FROM dbo.USUARIOS
    WHERE ID_USUARIO = :id
");
$stmt->execute([':id' => $uid]);
$rutaFirmaRel = (string)($stmt->fetchColumn() ?: '');

// 3.2 Calcular ruta física igual que en subd_firma_save.php
$firmaExiste = false;
$firmaImgSrc = '';

if ($rutaFirmaRel !== '') {
    // Mismo cálculo de raíz del proyecto que en subd_firma_save.php
    $PROJ_ROOT = str_replace('\\', '/', dirname(__DIR__, 2)); // sube 2 niveles desde app/php
    $rutaFirmaFis = $PROJ_ROOT . '/' . $rutaFirmaRel;

    $firmaExiste = file_exists($rutaFirmaFis);

    if ($firmaExiste) {
        // Ruta pública (ajusta si tu raíz web es distinta)
        $firmaImgSrc = '/SIGED/' . $rutaFirmaRel . '?v=' . time();
    }
}

// ============================
// 4) Filtros (GET)
// ============================
$filtroTipo = strtoupper(trim((string)($_GET['tipo'] ?? '')));
$qTexto     = trim((string)($_GET['q'] ?? ''));

// armamos IN seguro porque usamos una lista constante, no input de usuario
$tiposListSql = "'" . implode("','", array_map('addslashes', $DOCS_SUBDIR)) . "'";

// ============================
// 5) Query de documentos aprobados que llevan su firma
// ============================
$params = [];
$sql = "
  SELECT
    S.ID_SOLICITUD           AS id,
    S.TIPO_DOCUMENTO         AS tipo,
    S.ESTADO                 AS estado,
    S.FECHA_CREACION         AS f_crea,
    S.FECHA_ENVIO            AS f_env,
    S.FECHA_DECISION         AS f_dec,
    S.FOLIO                  AS folio,
    S.RUTA_PDF               AS ruta_pdf,
    D.NOMBRE_DOCENTE         AS nom_doc,
    D.APELLIDO_PATERNO_DOCENTE  AS ap_doc,
    D.APELLIDO_MATERNO_DOCENTE  AS am_doc,
    P.NOMBRE                 AS nombre_plantilla,
    P.CODIGO_EVIDENCIA       AS codigo_evidencia
  FROM dbo.SOLICITUD_DOCUMENTO S
  JOIN dbo.DOCENTE D        ON D.ID_DOCENTE = S.ID_DOCENTE
  LEFT JOIN dbo.PLANTILLA_DOC P ON P.TIPO_DOCUMENTO = S.TIPO_DOCUMENTO AND P.ACTIVO = 1
  WHERE S.ESTADO = 'APROBADA'
    AND S.TIPO_DOCUMENTO IN ($tiposListSql)
";



if ($filtroTipo !== '' && in_array($filtroTipo, $DOCS_SUBDIR, true)) {
  $sql .= " AND S.TIPO_DOCUMENTO = :ftipo";
  $params[':ftipo'] = $filtroTipo;
}

if ($qTexto !== '') {
  // CORRECCIÓN: Usar nombres únicos para cada campo (:q1, :q2, etc.)
  $sql .= " AND (
              D.NOMBRE_DOCENTE LIKE :q1
           OR D.APELLIDO_PATERNO_DOCENTE LIKE :q2
           OR D.APELLIDO_MATERNO_DOCENTE LIKE :q3
           OR S.FOLIO LIKE :q4
           OR S.TIPO_DOCUMENTO LIKE :q5
         )";
  
  $term = '%' . $qTexto . '%';
  $params[':q1'] = $term;
  $params[':q2'] = $term;
  $params[':q3'] = $term;
  $params[':q4'] = $term;
  $params[':q5'] = $term;
}

$sql .= " ORDER BY S.FECHA_DECISION DESC, S.ID_SOLICITUD DESC";

$st = $pdo->prepare($sql);
$st->execute($params);
$docs = $st->fetchAll(PDO::FETCH_ASSOC);

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>SIGED · Documentos con firma del Subdirector</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/SIGED/public/css/subd_docs.css">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>
  <!-- HEADER -->
  <header class="header">
    <div class="header-left">
      <div class="menu-icon" id="menuToggle">
        <span></span><span></span><span></span>
      </div>
    </div>
    <div class="header-center">
      <a href="/SIGED/public/index.php?action=home_subdirector" title="SIGED - Inicio">
        <img src="/SIGED/public/img/IconosSIged/Recurso 3SIGED_LOGO.png" alt="SIGED Logo" class="header-logo">
      </a>
    </div>
    <div class="header-right">
      <button class="btn-salir" onclick="location.href='/SIGED/public/index.php?action=logout'">Salir</button>
    </div>
  </header>

  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <nav class="sidebar-nav">
      <ul>
        <li>
          <a href="/SIGED/public/index.php?action=subd_docs" class="nav-link active">
            <img src="/SIGED/public/img/IconosSIged/Recurso 5Icono_usuario.png" alt="Docs" class="nav-img">
            <span class="nav-text">Inicio</span>
          </a>
        </li>
      </ul>
    </nav>
    <div class="sidebar-footer">
      <div class="user-info">
        <div class="user-avatar-small">
          <img src="/SIGED/public/img/User.png" alt="Usuario" class="user-avatar-img-small">
        </div>
        <div class="user-details">
          <span class="user-name"><?= htmlspecialchars($nombreCompleto) ?></span>
        </div>
      </div>
    </div>
  </aside>

  <!-- CONTENIDO PRINCIPAL -->
  <main class="main-content">
    <div class="content-wrapper">

      <!-- BLOQUE FIRMA SUBDIRECTOR -->
      <section class="firma-card">
        <div class="firma-preview">
          <?php if ($firmaExiste): ?>
            <img src="<?= htmlspecialchars($firmaImgSrc) ?>" alt="Firma Subdirector">
          <?php else: ?>
            <span style="font-size:.8rem;color:#6b7280">Sin firma cargada</span>
          <?php endif; ?>
        </div>
        <div>
          <h2 class="titulo-seccion" style="margin-top:0">
            <i class='bx bx-pen'></i> Mi firma (Subdirector Académico)
          </h2>
          <p style="margin:.25rem 0 0;font-size:.9rem;color:#4b5563">
            Esta firma se utilizará automáticamente en los documentos donde se requiera la firma del Subdirector Académico.
          </p>

          <form method="post"
                action="/SIGED/public/index.php?action=subd_firma_save"
                enctype="multipart/form-data"
                style="margin-top:.75rem;display:flex;flex-wrap:wrap;gap:.75rem;align-items:center">
            
            <!-- Input estilizado -->
            <input type="file" name="firma" accept="image/png,image/jpeg" required 
                   style="padding: 8px; border: 1px solid #e5e7eb; border-radius: 8px; background: #fff;">
            
            <!-- BOTÓN ACTUALIZADO: Subir Firma -->
            <button type="submit" class="button-azul-completo" style="width: auto; padding: 10px 20px;">
              <i class='bx bx-cloud-upload'></i> Actualizar firma
            </button>
          </form>

          <?php if (isset($_GET['firma_ok'])): ?>
            <div class="alert" style="margin-top:.5rem;background:#ecfdf3;border:1px solid #bbf7d0;color:#166534;padding:.5rem;border-radius:.5rem">
              Firma actualizada correctamente.
            </div>
          <?php elseif (isset($_GET['firma_err'])): ?>
            <div class="alert" style="margin-top:.5rem;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:.5rem;border-radius:.5rem">
              Ocurrió un problema al guardar la firma (código: <?= htmlspecialchars((string)$_GET['firma_err']) ?>).
            </div>
          <?php endif; ?>
        </div>
      </section>

      <!-- FILTROS -->
      <section class="filtros-card">
        <h2 class="titulo-seccion"><i class='bx bx-filter'></i> Filtros</h2>
        <form method="get" action="/SIGED/public/index.php" class="filtros-grid">
          <input type="hidden" name="action" value="subd_docs">

          <div class="filtro-item">
            <label>Tipo de documento</label>
            <select class="filtro-select" name="tipo">
              <option value="">Todos</option>
              <?php foreach ($DOCS_SUBDIR as $td): ?>
                <option value="<?= htmlspecialchars($td) ?>" <?= $td === $filtroTipo ? 'selected' : '' ?>>
                  <?= htmlspecialchars($td) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="filtro-item">
            <label>Buscar (folio / docente / tipo)</label>
            <input type="search" class="filtro-input" name="q"
                   value="<?= htmlspecialchars($qTexto) ?>"
                   placeholder="Escribe parte del nombre, folio o tipo...">
          </div>

          <div class="filtro-item" style="align-self:flex-end">
            <!-- BOTÓN ACTUALIZADO: Aplicar Filtros -->
            <button type="submit" class="button-azul-completo" style="width: auto; padding: 10px 20px;">
              <i class='bx bx-search'></i> Buscar
            </button>
          </div>
        </form>
      </section>

      <!-- TABLA DOCUMENTOS -->
      <section class="solicitudes-card">
        <div class="card-header-flex">
          <h2 class="titulo-seccion">
            <i class='bx bx-library'></i> Documentos aprobados con firma del Subdirector
          </h2>
        </div>

        <?php if (!$docs): ?>
          <div class="tabla-vacia">
            <i class='bx bx-inbox'></i>
            <p>No hay documentos aprobados que cumplan con los filtros seleccionados.</p>
          </div>
        <?php else: ?>
          <div class="tabla-container">
            <table class="tabla-solicitudes tabla-small">
              <thead>
                <tr>
                  <th>ID / Folio</th>
                  <th>Tipo</th>
                  <th>Docente</th>
                  <th>Fechas</th>
                  <th>PDF</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($docs as $r):
                $id    = (int)$r['id'];
                $folio = trim((string)($r['folio'] ?? ''));
                $tipo  = (string)$r['tipo'];
                $nomDoc = trim(
                  ($r['nom_doc'] ?? '') . ' ' .
                  ($r['ap_doc'] ?? '') . ' ' .
                  ($r['am_doc'] ?? '')
                );
                $fEnv = $r['f_env'] ? substr((string)$r['f_env'], 0, 19) : '—';
                $fDec = $r['f_dec'] ? substr((string)$r['f_dec'], 0, 19) : '—';
                $rutaPdf = trim((string)($r['ruta_pdf'] ?? ''));
              ?>
                <tr>
                  <td>
                    <div class="celda-id">#<?= $id ?></div>
                    <?php if ($folio): ?>
                      <div class="celda-folio">Folio: <?= htmlspecialchars($folio) ?></div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="badge badge-enviada">
                      <?= htmlspecialchars($tipo) ?>
                    </span>
                    <?php if (!empty($r['nombre_plantilla'])): ?>
                      <div style="font-size:.75rem;color:#6b7280">
                        <?= htmlspecialchars($r['nombre_plantilla']) ?>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?= htmlspecialchars($nomDoc ?: '—') ?>
                  </td>
                  <td>
                    <div class="fecha-info">
                      <i class='bx bx-paper-plane'></i> Envío: <?= htmlspecialchars($fEnv) ?>
                    </div>
                    <div class="fecha-info">
                      <i class='bx bx-check-circle'></i> Decisión: <?= htmlspecialchars($fDec) ?>
                    </div>
                  </td>
                  <td>
                    <?php if ($rutaPdf): ?>
                      <!-- BOTÓN ACTUALIZADO: Ver PDF -->
                      <a href="<?= htmlspecialchars($rutaPdf) ?>"
                         target="_blank"
                         class="button-azul-completo"
                         style="width: auto; padding: 6px 12px; font-size: 0.85rem; text-decoration: none; display: inline-flex;">
                        <i class='bx bxs-file-pdf'></i> Ver PDF
                      </a>
                    <?php else: ?>
                      <span class="badge badge-borrador" style="font-size:.75rem">
                        Sin PDF generado
                      </span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>

    </div>
  </main>

  <script>
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    if (menuToggle && sidebar) {
      menuToggle.addEventListener('click', () => {
        sidebar.classList.toggle('minimized');
      });
    }
  </script>
</body>
</html>
