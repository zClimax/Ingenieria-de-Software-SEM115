<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';

requireRole(['JEFE_DEPARTAMENTO']);
Session::start();

$pdo = DB::conn();

$S = Config::MAP['SOLICITUD'];
$E = Config::MAP['EVID'];
$D = Config::MAP['DOCENTE'];

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die('ID inválido');
}

// ==== Solicitud (misma base que la versión original) ====
$sol = $pdo->query("
    SELECT 
      {$S['ID']}     AS id,
      {$S['TIPO']}   AS tipo,
      {$S['ESTADO']} AS estado,
      {$S['DOC']}    AS id_doc
    FROM {$S['TABLE']}
    WHERE {$S['ID']} = {$id}
")->fetch(PDO::FETCH_ASSOC);

if (!$sol) {
    die('Solicitud no encontrada');
}

$tipoRaw     = (string)($sol['tipo']   ?? '');
$estadoRaw   = (string)($sol['estado'] ?? '');
$tipoUpper   = strtoupper($tipoRaw);
$estadoUpper = strtoupper($estadoRaw);

// ==== Evidencias ====
$ev = $pdo->query("
    SELECT {$E['ID']} AS id, {$E['NOM']} AS nombre 
    FROM {$E['TABLE']} 
    WHERE {$E['SOL']} = {$id}
")->fetchAll(PDO::FETCH_ASSOC);

// ==== Docente ====
$doc = $pdo->query("
    SELECT 
      {$D['NOMBRE']} AS nom,
      {$D['AP_PAT']} AS ap,
      {$D['AP_MAT']} AS am
    FROM {$D['TABLE']}
    WHERE {$D['ID']} = ".(int)$sol['id_doc']
)->fetch(PDO::FETCH_ASSOC);

$nombreDoc = trim(
    ($doc['nom'] ?? '') . ' ' .
    ($doc['ap']  ?? '') . ' ' .
    ($doc['am']  ?? '')
);

// ==== Usuario actual (para foto y nombre en sidebar) ====
$user        = (array)(Session::user() ?? []);
$idUsuario   = (int)($user['id'] ?? 0);
$displayName = trim((string)($user['nombre'] ?? 'Jefe de Departamento'));

// Foto de perfil
$rutaFoto   = 'storage/fotos/jefe_' . $idUsuario . '.jpg';
$rutaFisica = __DIR__ . '/../../../' . $rutaFoto;
if (file_exists($rutaFisica)) {
    $imgSrc = '../' . $rutaFoto . '?v=' . time();
} else {
    $imgSrc = '/siged/public/img/User.png';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revisión de Solicitud | SIGED</title>
    
    <link rel="stylesheet" href="/siged/public/css/JefeVer.css">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>

    <!-- ENCABEZADO -->
    <header class="header">
        <div class="header-left">
            <div class="menu-container" id="menuToggle">
                <div class="menu-icon">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        </div>
        <div class="header-center">
            <a href="/siged/public/index.php?action=home_jefe">
                <img src="/siged/public/img/IconosSIged/Recurso%203SIGED_LOGO.png" alt="SIGED" class="site-logo">
            </a>
        </div>
        <div class="header-right">
            <button class="btn-salir" onclick="window.location.href='/siged/public/index.php?action=logout'">Salir</button>
        </div>
    </header>

    <!-- BARRA LATERAL -->
    <aside class="sidebar" id="sidebar">
        <nav class="sidebar-nav">
            <ul>
                <li>
                    <a href="/siged/public/index.php?action=home_jefe" class="nav-link">
                        <img src="/siged/public/img/IconosSIged/Recurso 5Icono_usuario.png" alt="Inicio" class="nav-img">
                        <span class="nav-text">Inicio</span>
                    </a>
                </li>
                <li>
                    <a href="/siged/public/index.php?action=jefe_bandeja" class="nav-link active">
                        <img src="/siged/public/img/IconosSIged/Recurso 6Icono_GActas.png" alt="Bandeja" class="nav-img">
                        <span class="nav-text">Bandeja</span>
                    </a>
                </li>
                <li>
                    <a href="/siged/public/index.php?action=tkj_list" class="nav-link">
                        <img src="/siged/public/img/IconosSIged/Recurso 7Icono_tickets.png" alt="Tickets" class="nav-img">
                        <span class="nav-text">Tickets</span>
                    </a>
                </li>
            </ul>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar-small">
                    <img src="<?= htmlspecialchars($imgSrc) ?>" alt="Usuario" class="user-avatar-img-small">
                </div>
                <div class="user-details">
                    <span class="user-name"><?= htmlspecialchars($displayName) ?></span>
                </div>
            </div>
        </div>
    </aside>

    <!-- CONTENIDO PRINCIPAL -->
    <main class="main-content" id="mainContent">
        <div class="revision-wrapper">

            <!-- Header de revisión -->
            <section class="revision-header">
                <div class="breadcrumb">
                    <a href="/siged/public/index.php?action=jefe_bandeja">
                        <i class='bx bx-folder-open'></i>
                        Bandeja
                    </a>
                    <i class='bx bx-chevron-right'></i>
                    <span>Revisión de solicitud</span>
                </div>

                <h1 class="revision-title">
                    <span class="solicitud-badge">#<?= (int)$sol['id'] ?></span>
                    <span class="tipo-badge"><?= htmlspecialchars($tipoUpper) ?></span>
                </h1>

                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Docente</span>
                        <span class="info-value">
                            <i class='bx bx-user'></i>
                            <?= htmlspecialchars($nombreDoc) ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Estado actual</span>
                        <?php
                        $estadoClass = match ($estadoUpper) {
                            'APROBADA'  => 'aprobada',
                            'RECHAZADA' => 'rechazada',
                            'ENVIADA'   => 'enviada',
                            default     => 'otro'
                        };
                        $iconEstado = match ($estadoUpper) {
                            'APROBADA'  => 'bx-check-circle',
                            'RECHAZADA' => 'bx-x-circle',
                            'ENVIADA'   => 'bx-time-five',
                            default     => 'bx-info-circle'
                        };
                        ?>
                        <span class="estado-badge <?= $estadoClass ?>">
                            <i class='bx <?= $iconEstado ?>'></i>
                            <?= htmlspecialchars($estadoRaw) ?>
                        </span>
                    </div>
                </div>
            </section>

            <!-- CI mount (como en la versión original) -->
            <section class="content-section">
                <?php require __DIR__ . '/ci_mount.php'; ?>
            </section>

            <!-- Evidencias -->
            <section class="content-section">
                <h3 class="section-title">
                    <i class='bx bx-paperclip'></i>
                    Evidencias adjuntas
                </h3>
                <?php if ($ev): ?>
                    <ul class="evidencias-list">
                        <?php foreach($ev as $f): ?>
                            <li class="evidencia-item">
                                <div class="evidencia-info">
                                    <div class="evidencia-icon">
                                        <i class='bx bx-file'></i>
                                    </div>
                                    <span class="evidencia-nombre"><?= htmlspecialchars($f['nombre']) ?></span>
                                </div>
                                <a href="/siged/public/index.php?action=sol_descargar&id=<?= (int)$f['id'] ?>" class="btn-download">
                                    <i class='bx bx-download'></i>
                                    Descargar
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="empty-state">
                        <i class='bx bx-file-blank'></i>
                        <p>Sin evidencias adjuntas</p>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Formularios específicos por tipo (misma lógica que la original) -->
            <?php if ($sol['estado'] === 'ENVIADA'): ?>
                <?php
                // ACI: formulario CI sólo si el tipo de documento es ACI
                if (($sol['TIPO_DOCUMENTO'] ?? '') === 'ACI') {
                    echo '<section class="content-section mounted-form-section">';
                    require __DIR__ . '/ci_form.php';
                    echo '</section>';
                }

                // RED: formulario del Depto (asignatura + programa)
                if (($sol['tipo'] ?? '') === 'RED') {
                    echo '<section class="content-section mounted-form-section">';
                    require __DIR__ . '/dep_form.php';
                    echo '</section>';
                }

                // ESTR
                if (($sol['tipo'] ?? $sol['TIPO_DOCUMENTO'] ?? '') === 'ESTR') {
                    echo '<section class="content-section mounted-form-section">';
                    require __DIR__ . '/estr_mount.php';
                    echo '</section>';
                }

                // TUT
                if (($sol['tipo'] ?? $sol['TIPO_DOCUMENTO'] ?? '') === 'TUT') {
                    echo '<section class="content-section mounted-form-section">';
                    require __DIR__ . '/tut_mount.php';
                    echo '</section>';
                }
                if (($sol['tipo'] ?? $sol['TIPO_DOCUMENTO'] ?? '') === 'CSE') {
                    echo '<div class="mounted-form-section">';
                    require __DIR__ . '/cse_mount.php';
                    echo '</div>';
                }
                if (($sol['tipo'] ?? $sol['TIPO_DOCUMENTO'] ?? '') === 'CCA') {
                    echo '<div class="mounted-form-section">';
                    require __DIR__ . '/cca_mount.php';
                    echo '</div>';
                }
                if (($sol['tipo'] ?? $sol['TIPO_DOCUMENTO'] ?? '') === 'LAD') {
                    echo '<div class="mounted-form-section">';
                    require __DIR__ . '/lad_form.php';
                    echo '</div>';
                }
                if (($sol['tipo'] ?? $sol['TIPO_DOCUMENTO'] ?? '') === 'CLFG') {
                    echo '<div class="mounted-form-section">';
                    require __DIR__ . '/clfg_form.php';
                    echo '</div>';
                }
                if (($sol['tipo'] ?? $sol['TIPO_DOCUMENTO'] ?? '') === 'CSE2') {
                    echo '<div class="mounted-form-section">';
                    require __DIR__ . '/cse_mount.php';
                    echo '</div>';
                }

                if (($sol['tipo'] ?? $sol['TIPO_DOCUMENTO'] ?? '') === 'CHA') {
                    echo '<div class="mounted-form-section">';
                    require __DIR__ . '/cha_mount.php';
                    echo '</div>';
                }
                if (($sol['tipo'] ?? $sol['TIPO_DOCUMENTO'] ?? '') === 'CSEP') {
                    echo '<div class="mounted-form-section">';
                    require __DIR__ . '/csep_mount.php';
                    echo '</div>';
                }
                if (($sol['tipo'] ?? $sol['TIPO_DOCUMENTO'] ?? '') === 'CSEM') {
                    echo '<div class="mounted-form-section">';
                    require __DIR__ . '/csem_mount.php';
                    echo '</div>';
                }
                if (($sol['tipo'] ?? $sol['TIPO_DOCUMENTO'] ?? '') === 'CPI') {
                    echo '<div class="mounted-form-section">';
                    require __DIR__ . '/cpi_mount.php';
                    echo '</div>';
                }
                if (($sol['tipo'] ?? $sol['TIPO_DOCUMENTO'] ?? '') === 'CMP') {
                    echo '<div class="mounted-form-section">';
                    require __DIR__ . '/cmp_mount.php';
                    echo '</div>';
                }
                if (($sol['tipo'] ?? $sol['TIPO_DOCUMENTO'] ?? '') === 'CMDI') {
                    echo '<div class="mounted-form-section">';
                    require __DIR__ . '/cmdi_mount.php';
                    echo '</div>';
                }
                if (($sol['tipo'] ?? $sol['TIPO_DOCUMENTO'] ?? '') === 'CCID') {
                    echo '<div class="mounted-form-section">';
                    require __DIR__ . '/ccid_mount.php';
                    echo '</div>';
                }
                if (($sol['tipo'] ?? $sol['TIPO_DOCUMENTO'] ?? '') === 'CCUI') {
                    echo '<div class="mounted-form-section">';
                    require __DIR__ . '/ccui_mount.php';
                    echo '</div>';
                }
                if (($sol['tipo'] ?? $sol['TIPO_DOCUMENTO'] ?? '') === 'CDPC') {
                    echo '<div class="mounted-form-section">';
                    require __DIR__ . '/cdpc_mount.php';
                    echo '</div>';
                }
                if (($sol['tipo'] ?? $sol['TIPO_DOCUMENTO'] ?? '') === 'CIPC') {
                    echo '<div class="mounted-form-section">';
                    require __DIR__ . '/cipc_mount.php';
                    echo '</div>';
                }
                if (($sol['tipo'] ?? $sol['TIPO_DOCUMENTO'] ?? '') === 'CPFT') {
                    echo '<div class="mounted-form-section">';
                    require __DIR__ . '/cpft_mount.php';
                    echo '</div>';
                }
                if (($sol['tipo'] ?? $sol['TIPO_DOCUMENTO'] ?? '') === 'CDRE') {
                    echo '<div class="mounted-form-section">';
                    require __DIR__ . '/cdre_mount.php';
                    echo '</div>';
                }
                if (($sol['tipo'] ?? $sol['TIPO_DOCUMENTO'] ?? '') === 'CDEI') {
                    echo '<div class="mounted-form-section">';
                    require __DIR__ . '/cdei_mount.php';
                    echo '</div>';
                }
                if (($sol['tipo'] ?? $sol['TIPO_DOCUMENTO'] ?? '') === 'CDPE') {
                    echo '<div class="mounted-form-section">';
                    require __DIR__ . '/cdpe_mount.php';
                    echo '</div>';
                }
                if (($sol['tipo'] ?? $sol['TIPO_DOCUMENTO'] ?? '') === 'CAE') {
                    echo '<div class="mounted-form-section">';
                    require __DIR__ . '/cae_mount.php';
                    echo '</div>';
                }
                if (($sol['tipo'] ?? $sol['TIPO_DOCUMENTO'] ?? '') === 'CST') {
                    echo '<div class="mounted-form-section">';
                    require __DIR__ . '/cst_mount.php';
                    echo '</div>';
                }
                if (($sol['tipo'] ?? $sol['TIPO_DOCUMENTO'] ?? '') === 'CCO') {
                    echo '<div class="mounted-form-section">';
                    require __DIR__ . '/cco_mount.php';
                    echo '</div>';
                }
                if (($sol['tipo'] ?? $sol['TIPO_DOCUMENTO'] ?? '') === 'CPP') {
                    echo '<div class="mounted-form-section">';
                    require __DIR__ . '/cpp_mount.php';
                    echo '</div>';
                }
                if (($sol['tipo'] ?? $sol['TIPO_DOCUMENTO'] ?? '') === 'CCE') {
                    echo '<div class="mounted-form-section">';
                    require __DIR__ . '/cce_mount.php';
                    echo '</div>';
                }
                if (($sol['tipo'] ?? $sol['TIPO_DOCUMENTO'] ?? '') === 'CJEA') {
                    echo '<div class="mounted-form-section">';
                    require __DIR__ . '/cjea_mount.php';
                    echo '</div>';
                }
                if (($sol['tipo'] ?? $sol['TIPO_DOCUMENTO'] ?? '') === 'CEPA') {
                    echo '<div class="mounted-form-section">';
                    require __DIR__ . '/cepa_mount.php';
                    echo '</div>';
                }
                if (($sol['tipo'] ?? $sol['TIPO_DOCUMENTO'] ?? '') === 'PASG') {
                    echo '<div class="mounted-form-section">';
                    require __DIR__ . '/pasg_mount.php';
                    echo '</div>';
                }
                
                ?>

                <!-- Decisión (corregida para mandar siempre "decision") -->
                <section class="decision-section">
                    <h3 class="section-title">
                        <i class='bx bx-check-shield'></i>
                        Decisión
                    </h3>

                    <form id="decisionForm" method="post" action="/siged/public/index.php?action=jefe_decidir" class="decision-form">
                        <input type="hidden" name="id" value="<?= (int)$sol['id'] ?>">
                        <input type="hidden" name="decision" id="decisionField" value="">

                        <div class="form-group">
                            <label class="form-label">
                                <i class='bx bx-message-square-detail'></i>
                                Comentario (opcional)
                            </label>
                            <textarea name="comentario" rows="3" class="form-textarea" placeholder="Ingrese sus observaciones..."></textarea>
                        </div>

                        <div class="decision-actions">
                            <button type="button" class="btn-primary" onclick="setDecisionAndSubmit('APROBADA')">
                                <i class='bx bx-check-circle'></i>
                                Aprobar (autoriza firma)
                            </button>

                            <button type="button" class="btn-danger" onclick="setDecisionAndSubmit('RECHAZADA')">
                                <i class='bx bx-x-circle'></i>
                                Rechazar
                            </button>
                        </div>
                    </form>
                </section>
            <?php endif; ?>

            <!-- PDF cuando ya está aprobada -->
            <?php if ($sol['estado'] === 'APROBADA'): ?>
    <section class="content-section">
        <h3 class="section-title">
            <i class='bx bx-file-pdf'></i>
            Documento final
        </h3>
        <a target="_blank"
           href="/SIGED/public/index.php?action=doc_pdf&id=<?= (int)$sol['id'] ?>"
           class="pdf-link">
            <i class='bx bx-download'></i>
            Generar / Ver PDF
        </a>
    </section>
<?php endif; ?>

            <!-- Navegación -->
            <div class="back-section">
                <a href="/siged/public/index.php?action=jefe_bandeja" class="btn-back">
                    <i class='bx bx-arrow-back'></i>
                    Volver a Bandeja
                </a>
            </div>

        </div>
    </main>

    <!-- Scripts -->
    <script src="/siged/public/js/menu.js"></script>
    <script src="/siged/public/js/jefe_ver.js"></script>

    <script>
    function setDecisionAndSubmit(decision) {
        var dField = document.getElementById('decisionField');
        var form   = document.getElementById('decisionForm');
        if (!dField || !form) return;
        dField.value = decision; // APROBADA o RECHAZADA
        form.submit();
    }
    </script>
</body>
</html>
