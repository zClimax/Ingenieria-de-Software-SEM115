<?php
// filepath: c:\xampp\htdocs\SIGED\app\php\tickets\crear.php
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

$sol = isset($_GET['sol']) ? (int)$_GET['sol'] : 0;

$jsData = json_encode([
    'userId' => $uid,
    'nombreCompleto' => $nombreCompleto,
    'solId' => $sol
]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Ticket | SIGED</title>
    
    <link rel="stylesheet" href="/SIGED/public/css/CrearTickets.css">
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
            <a href="/SIGED/public/index.php?action=home_docente">
                <img src="/SIGED/public/img/IconosSIged/Recurso%203SIGED_LOGO.png" alt="SIGED" class="site-logo">
            </a>
        </div>
        <div class="header-right">
            <!-- <a href="/SIGED/public/index.php?action=notificaciones">
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
        <div class="content-wrapper form-wrapper">
            
            <h1 class="form-title">Crear Ticket</h1>

            <?php if ($sol > 0): ?>
                <div class="info-banner">
                    <i class='bx bx-info-circle'></i>
                    <span>Este ticket está relacionado con la solicitud #<?php echo $sol; ?></span>
                </div>
            <?php endif; ?>

            <form action="/SIGED/public/index.php?action=tk_guardar" method="post" class="ticket-form">
                
                <?php if ($sol > 0): ?>
                    <input type="hidden" name="sol" value="<?php echo (int)$sol; ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="titulo" class="form-label">
                        <i class='bx bx-edit-alt'></i>
                        Título del ticket
                    </label>
                    <input 
                        type="text" 
                        id="titulo" 
                        name="titulo" 
                        class="form-input" 
                        required 
                        placeholder="Ej: Error al generar constancia"
                        maxlength="200"
                    >
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="tipo" class="form-label">
                            <i class='bx bx-category'></i>
                            Tipo
                        </label>
                        <select id="tipo" name="tipo" class="form-select">
                            <option value="DOCUMENTO">Documento</option>
                            <option value="FIRMA">Firma</option>
                            <option value="PDF">PDF</option>
                            <option value="OTRO" selected>Otro</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="prioridad" class="form-label">
                            <i class='bx bx-flag'></i>
                            Prioridad
                        </label>
                        <select id="prioridad" name="prioridad" class="form-select">
                            <option value="BAJA">Baja</option>
                            <option value="MEDIA" selected>Media</option>
                            <option value="ALTA">Alta</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="responsable" class="form-label">
                        <i class='bx bx-user-check'></i>
                        Responsable (Jefe de Departamento)
                    </label>
                    <select id="responsable" name="responsable" class="form-select" required>
                        <option value="">Cargando jefes de departamento...</option>
                    </select>
                    <div class="form-hint" id="sugerencia">
                        <i class='bx bx-info-circle'></i>
                        <span>Seleccione el jefe responsable del departamento correspondiente.</span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="descripcion" class="form-label">
                        <i class='bx bx-message-square-detail'></i>
                        Descripción del problema
                    </label>
                    <textarea 
                        id="descripcion" 
                        name="descripcion" 
                        class="form-textarea" 
                        required 
                        placeholder="Describe detalladamente el problema o solicitud..."
                        rows="6"
                    ></textarea>
                    <div class="form-hint">
                        <span>Incluye todos los detalles relevantes para resolver el ticket más rápidamente.</span>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-submit">
                        <i class='bx bx-check-circle'></i>
                        Crear Ticket
                    </button>
                    <a href="/SIGED/public/index.php?action=tk_list" class="btn-cancel">
                        <i class='bx bx-x-circle'></i>
                        Cancelar
                    </a>
                </div>

            </form>
                                    <!-- Lista de evidencias -->
                <?php if (empty($evidencias)): ?>
                    <div class="no-comments">
                        <i class='bx bx-folder-open'></i>
                        <p>No hay evidencias cargadas aún.</p>
                    </div>
                <?php else: ?>
                    <div class="lista-evidencias">
                        <?php foreach ($evidencias as $ev): ?>
                            <div class="item-evidencia">
                                <div class="info-evidencia">
                                    <i class='bx bxs-file-pdf'></i>
                                    <span class="nombre-evidencia">
                                        <?= htmlspecialchars($ev['nombre']) ?>
                                    </span>
                                </div>
                                <div class="acciones-evidencia">
                                    <a 
                                        href="/SIGED/public/index.php?action=tk_evid_descargar&id=<?= (int)$ev['id'] ?>"
                                        class="boton-icono boton-descargar"
                                        title="Descargar"
                                    >
                                        <i class='bx bx-download'></i>
                                    </a>

                                    <?php if ($tk['ESTATUS'] !== 'CERRADO'): ?>
                                        <button 
                                            type="button"
                                            class="boton-icono boton-eliminar"
                                            data-id="<?= (int)$ev['id'] ?>"
                                            title="Eliminar"
                                        >
                                            <i class='bx bx-trash'></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>



        </div>
    </main>

    <script>
        window.APP_DATA = <?php echo $jsData; ?>;
    </script>
    <script src="/SIGED/public/js/menu.js"></script>
    <script src="/SIGED/public/js/crear_ticket.js"></script>
</body>
</html>