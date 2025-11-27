<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';

Session::start();
requireRole(['JEFE_DEPARTAMENTO']);

$user = Session::user();
$idUsuario = (int)$user['id'];

// Foto de perfil
$rutaFoto = 'storage/fotos/jefe_' . $idUsuario . '.jpg';
$rutaFisica = __DIR__ . '/../../../' . $rutaFoto;
if (file_exists($rutaFisica)) {
    $imgSrc = '../' . $rutaFoto . '?v=' . time();
} else {
    $imgSrc = '/SIGED/public/img/User.png';
}

$displayName = trim((string)($user['nombre'] ?? 'Jefe de Departamento'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tickets | SIGED</title>
    
    <link rel="stylesheet" href="/SIGED/public/css/TicketsJefe.css">
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
                    <a href="/SIGED/public/index.php?action=home_jefe" class="nav-link">
                        <img src="/SIGED/public/img/IconosSIged/Recurso 5Icono_usuario.png" alt="Inicio" class="nav-img">
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
                    <a href="/SIGED/public/index.php?action=tkj_list" class="nav-link active">
                        <img src="/SIGED/public/img/IconosSIged/Recurso 7Icono_tickets.png" alt="Tickets" class="nav-img">
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
        <div class="tickets-wrapper">

            <!-- Header -->
            <section class="tickets-header">
                <h1 class="page-title">
                    <i class='bx bx-support'></i>
                    Tickets asignados
                </h1>
                <p class="page-subtitle">Gestiona las solicitudes de soporte de los docentes</p>
            </section>

            <!-- Tabs y Tabla -->
            <section class="tabs-section">
                <div class="tabs-container">
                    <button class="tab active" data-t="abiertos">
                        <i class='bx bx-folder-open'></i>
                        Abiertos
                    </button>
                    <button class="tab" data-t="revision">
                        <i class='bx bx-time-five'></i>
                        En revisión
                    </button>
                    <button class="tab" data-t="cerrados">
                        <i class='bx bx-check-circle'></i>
                        Cerrados
                    </button>
                </div>

                <div class="table-container">
                    <table class="tickets-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>ID</th>
                                <th>Título</th>
                                <th>Docente</th>
                                <th>Prioridad</th>
                                <th>Estatus</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody id="tb">
                            <tr>
                                <td colspan="7" class="loading">
                                    <i class='bx bx-loader-alt'></i>
                                    <p>Cargando tickets...</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

        </div>
    </main>

    <!-- Scripts -->
    <script src="/SIGED/public/js/menu.js"></script>
    <script src="/SIGED/public/js/tickets_jefe.js"></script>
</body>
</html>
