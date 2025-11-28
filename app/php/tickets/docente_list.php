<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../utils/roles.php';

Session::start();
requireRole(['DOCENTE']);

$user = Session::user();
$nombreCompleto = $user['nombre'] ?? 'Usuario';
$uid = (int)($user['id'] ?? 0);

// Foto de perfil
$rutaFoto = 'storage/fotos/doc_' . $uid . '.jpg';
$rutaFisica = __DIR__ . '/../../../' . $rutaFoto;
if (file_exists($rutaFisica)) {
    $imgSrc = '../' . $rutaFoto . '?v=' . time();
} else {
    $imgSrc = '/SIGED/public/img/User.png';
}

// Pasar datos a JavaScript
$jsData = json_encode([
    'userId' => $uid,
    'nombreCompleto' => $nombreCompleto
]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tickets | SIGED</title>
    
    <link rel="stylesheet" href="/SIGED/public/css/Tickets.css">
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
            <a href="/SIGED/public/index.php?action=home_docente" title="SIGED - Inicio">
                <img src="/SIGED/public/img/IconosSIged/Recurso%203SIGED_LOGO.png" alt="SIGED" class="site-logo">
            </a>
        </div>
        <div class="header-right">
            <button class="btn-salir" onclick="window.location.href='/SIGED/public/index.php?action=logout'">Salir</button>
        </div>
    </header>

    <!-- BARRA LATERAL -->
    <aside class="sidebar" id="sidebar">
        <nav class="sidebar-nav">
            <ul>
                <li>
                    <a href="/SIGED/public/index.php?action=home_docente" class="nav-link">
                        <img src="/SIGED/public/img/IconosSIged/Recurso 5Icono_usuario.png" alt="Inicio" class="nav-img">
                        <span class="nav-text">Inicio</span>
                    </a>
                </li>
                <li>
                    <a href="/SIGED/public/index.php?action=sol_mis" class="nav-link">
                        <img src="/SIGED/public/img/IconosSIged/Recurso 6Icono_GActas.png" alt="Mis Solicitudes" class="nav-img">
                        <span class="nav-text">Mis Solicitudes</span>
                    </a>
                </li>
                <li>
                    <a href="/SIGED/public/index.php?action=doc_firma" class="nav-link">
                        <img src="/SIGED/public/img/IconosSIged/Recurso 6Icono_firma.svg" alt="Mi Firma" class="nav-img">
                        <span class="nav-text">Mi Firma</span>
                    </a>
                </li>
                <li>
                    <a href="/SIGED/public/index.php?action=tk_list" class="nav-link active">
                        <img src="/SIGED/public/img/IconosSIged/Recurso 7Icono_tickets.png" alt="Tickets" class="nav-img">
                        <span class="nav-text">Tickets</span>
                    </a>
                </li>
            </ul>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar-small">
                    <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="Usuario" class="user-avatar-img-small">
                </div>
                <div class="user-details">
                    <span class="user-name"><?php echo htmlspecialchars($nombreCompleto); ?></span>
                </div>
            </div>
        </div>
    </aside>

    <!-- CONTENIDO PRINCIPAL -->
    <main class="main-content" id="mainContent">
        <div class="content-wrapper tickets-wrapper">
            
            <!-- Título -->
            <h1 class="tickets-title">Tickets</h1>

            <!-- Tabs de navegación -->
            <div class="tabs-container">
                <button class="tab active" data-t="abiertos">Abiertos</button>
                <button class="tab" data-t="cerrados">Cerrados</button>
            </div>

            <!-- Tabla de tickets -->
            <div class="table-container">
                <table class="tickets-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Clave</th>
                            <th>Descripción</th>
                            <th>Responsable</th>
                            <th>Departamento</th>
                            <th>Estatus</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="tb">
                        <tr>
                            <td colspan="7" style="text-align:center; padding:40px; color:#6b7280;">
                                <i class='bx bx-loader-alt bx-spin' style="font-size:2rem;"></i>
                                <p>Cargando tickets...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Acciones -->
            <div class="actions">
                <a href="/SIGED/public/index.php?action=tk_crear" class="btn-crear-ticket">
                    <i class='bx bx-plus-circle'></i>
                    Crear Ticket
                </a>
            </div>

        </div>
    </main>

    <!-- Scripts -->
    <script>
        window.APP_DATA = <?php echo $jsData; ?>;
    </script>
    <script src="/SIGED/public/js/menu.js"></script>
    <script src="/SIGED/public/js/tickets.js"></script>
</body>
</html>
