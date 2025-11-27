<?php
// filepath: c:\xampp\htdocs\SIGED\app\php\usuarios\home_jefe.php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';

Session::start();
$user = Session::user();

// Traer firma vigente y nombre completo desde BD
$pdo = DB::conn();
$uId = (int)($user['id'] ?? 0);
$firmaUrl = '';
$displayName = trim((string)($user['nombre'] ?? 'Jefe de Departamento'));

if ($uId > 0) {
  $st = $pdo->prepare("
    SELECT NOMBRE_COMPLETO, RUTA_FIRMA
    FROM [SIGED].[dbo].[USUARIOS]
    WHERE ID_USUARIO = :id
  ");
  $st->execute([':id'=>$uId]);
  if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
    $firmaUrl = (string)($row['RUTA_FIRMA'] ?? '');
    $full = trim((string)($row['NOMBRE_COMPLETO'] ?? ''));
    if ($full !== '') { $displayName = $full; }
  }
}

// Foto de perfil
$rutaFoto = 'storage/fotos/jefe_' . $uId . '.jpg';
$rutaFisica = __DIR__ . '/../../../' . $rutaFoto;
if (file_exists($rutaFisica)) {
    $imgSrc = '../' . $rutaFoto . '?v=' . time();
} else {
    $imgSrc = '/SIGED/public/img/User.png';
}

// Mensaje flash simple (por querystring)
$msg = $_GET['msg'] ?? '';
function msgText(string $m): string {
  return match($m) {
    'firma_ok'     => 'Firma actualizada correctamente.',
    'firma_tipo'   => 'Formato no válido (usa PNG o JPG).',
    'firma_pesada' => 'Archivo demasiado grande (máx. 2 MB).',
    'firma_error'  => 'No se pudo recibir el archivo.',
    'foto_ok'      => 'Foto de perfil actualizada correctamente.',
    'foto_tipo'    => 'Formato no válido (usa JPG).',
    'foto_pesada'  => 'Archivo demasiado grande (máx. 2 MB).',
    'foto_error'   => 'No se pudo cargar la foto.',
    default        => ''
  };
}

// Pasar datos a JavaScript
$jsData = json_encode([
    'userId' => $uId,
    'nombreCompleto' => $displayName
]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | SIGED - Jefe de Departamento</title>
    
    <link rel="stylesheet" href="/SIGED/public/css/HomeJefe.css">
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
            <a href="/SIGED/public/index.php?action=home_jefe">
                <img src="/SIGED/public/img/IconosSIged/Recurso%203SIGED_LOGO.png" alt="SIGED" class="site-logo">
            </a>
        </div>
        <div class="header-right">
            <!-- <a href="/SIGED/public/index.php?action=notificaciones" title="Notificaciones">
                <i class='bx bx-bell'></i>
            </a> -->
            <button class="btn-salir" onclick="window.location.href='/SIGED/public/index.php?action=logout'">Salir</button>
        </div>
    </header>

    <!-- BARRA LATERAL -->
    <aside class="sidebar" id="sidebar">
        <nav class="sidebar-nav">
            <ul>
                <li>
                    <a href="/SIGED/public/index.php?action=home_jefe" class="nav-link active">
                        <img src="/SIGED/public/img/IconosSIged/Recurso 5Icono_usuario.png" alt="Dashboard" class="nav-img">
                        <span class="nav-text">Inicio</span>
                    </a>
                </li>
                <li>
                    <a href="/SIGED/public/index.php?action=jefe_bandeja" class="nav-link">
                        <img src="/SIGED/public/img/IconosSIged/Recurso 6Icono_GActas.png" alt="Bandeja" class="nav-img">
                        <span class="nav-text">Bandeja</span>
                    </a>
                </li>
                <li>
                    <a href="/SIGED/public/index.php?action=tkj_list" class="nav-link">
                        <img src="/SIGED/public/img/IconosSIged/Recurso 7Icono_tickets.png" alt="Tickets" class="nav-img">
                        <span class="nav-text">Tickets</span>
                    </a>
                </li>
                
            </ul>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar-small">
                    <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="Usuario" class="user-avatar-img-small" id="avatarImgSmall">
                </div>
                <div class="user-details">
                    <span class="user-name"><?php echo htmlspecialchars($displayName); ?></span>
                </div>
            </div>
        </div>
    </aside>

    <!-- CONTENIDO PRINCIPAL -->
    <main class="main-content" id="mainContent">
        <div class="dashboard-wrapper">

            <!-- Mensaje de bienvenida -->
            <section class="welcome-card">
                <div class="welcome-header">
                    <div class="welcome-left">
                        <div class="welcome-avatar" id="avatarContainer">
                            <img src="<?= htmlspecialchars($imgSrc) ?>" alt="Foto de perfil" class="welcome-avatar-img" id="avatarImg">
                            <div class="avatar-overlay">
                                <i class='bx bx-camera'></i>
                                <span>Cambiar foto</span>
                            </div>
                        </div>
                        <div class="welcome-text">
                            <h1 class="welcome-title">Bienvenido(a), <?= htmlspecialchars($displayName) ?></h1>
                            <p class="welcome-subtitle">Panel de Control del Jefe de Departamento</p>
                        </div>
                    </div>
                    <div class="welcome-date">
                        <i class='bx bx-calendar'></i>
                        <span id="currentDate"></span>
                    </div>
                </div>

                <?php if ($msgTxt = msgText($msg)): ?>
                    <div class="alert-info">
                        <i class='bx bx-info-circle'></i>
                        <span><?= htmlspecialchars($msgTxt) ?></span>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Formulario oculto para foto (fuera de la card) -->
            <form id="fotoForm" method="post" action="/SIGED/public/index.php?action=jefe_foto_upload" enctype="multipart/form-data" style="display:none;">
                <input type="file" name="foto" id="fotoInput" accept="image/jpeg, image/jpg">
            </form>

            <!-- Firma Digital -->
            <section class="firma-section">
                <div class="firma-display">
                    <h2 class="section-title">
                        <i class='bx bx-pen'></i>
                        Tu firma digital
                    </h2>
                    <div class="firma-preview">
                        <?php if ($firmaUrl): ?>
                            <img src="<?= htmlspecialchars($firmaUrl) ?>" alt="Firma digital" class="firma-img">
                        <?php else: ?>
                            <div class="firma-empty">
                                <i class='bx bx-image-add'></i>
                                <p>Sin firma cargada</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <p class="firma-hint">
                        <i class='bx bx-info-circle'></i>
                        Recomendado: PNG con fondo transparente, aprox. 900×300 px. Peso máx. 2 MB.
                    </p>
                </div>

                <div class="firma-upload">
                    <h2 class="section-title">
                        <i class='bx bx-upload'></i>
                        Actualizar firma
                    </h2>
                    <form method="post" action="/SIGED/public/index.php?action=jefe_firma_guardar" enctype="multipart/form-data" class="upload-form">
                        <div class="upload-area">
                            <input type="file" name="firma" id="firma" accept="image/png, image/jpeg" required class="file-input">
                            <label for="firma" class="file-label">
                                <i class='bx bx-cloud-upload'></i>
                                <span>Seleccionar archivo</span>
                            </label>
                            <div class="file-name" id="fileName">Ningún archivo seleccionado</div>
                        </div>
                        <button type="submit" class="btn-submit">
                            <i class='bx bx-save'></i>
                            Guardar firma
                        </button>
                    </form>
                </div>
            </section>

            <!-- KPIs Grid -->
            <section class="kpis-section">
                <h2 class="section-title">
                    <i class='bx bx-chart'></i>
                    Indicadores clave
                </h2>
                <div class="kpis-grid" id="kpis">
                    <div class="kpi-card skeleton">
                        <div class="kpi-icon"><i class='bx bx-loader-alt bx-spin'></i></div>
                        <div class="kpi-label">Cargando...</div>
                        <div class="kpi-value">—</div>
                    </div>
                </div>
            </section>

            <!-- Gráfica + Tickets -->
            <section class="charts-section">
                <div class="chart-card">
                    <h2 class="section-title">
                        <i class='bx bx-line-chart'></i>
                        Tendencia de decisiones (30 días)
                    </h2>
                    <div id="trend" class="chart-container"></div>
                </div>

                <div class="tickets-summary-card">
                    <h2 class="section-title">
                        <i class='bx bx-support'></i>
                        Tickets
                    </h2>
                    <ul id="tickets" class="tickets-summary">
                        <li class="skeleton">Cargando...</li>
                    </ul>
                    <a href="/SIGED/public/index.php?action=tkj_list" class="link-ver-todos">
                        Ver todos los tickets <i class='bx bx-right-arrow-alt'></i>
                    </a>
                </div>
            </section>

            <!-- Backlog -->
            <section class="backlog-section">
                <h2 class="section-title">
                    <i class='bx bx-task'></i>
                    Backlog prioritario (pendientes)
                </h2>
                <div class="table-container">
                    <table id="backlog" class="data-table">
                        <thead>
                            <tr>
                                <th>Solicitud</th>
                                <th>Tipo documento</th>
                                <th>Estado</th>
                                <th>Días pendientes</th>
                                <th>Enviado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="5" class="loading-cell">
                                    <i class='bx bx-loader-alt bx-spin'></i>
                                    Cargando backlog...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="backlog-actions">
                    <a href="/SIGED/public/index.php?action=jefe_bandeja" class="btn-secondary">
                        <i class='bx bx-folder-open'></i>
                        Ir a Bandeja completa
                    </a>
                </div>
            </section>

        </div>
    </main>

    <!-- Scripts -->
    <script>
        window.APP_DATA = <?php echo $jsData; ?>;
    </script>
    <script src="/SIGED/public/js/menu.js"></script>
    <script src="/SIGED/public/js/home_jefe.js"></script>
</body>
</html>
