<?php
// filepath: c:\xampp\htdocs\SIGED\app\php\tickets\ver.php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php';
Session::start();
require_once __DIR__ . '/../utils/roles.php';
requireRole(['DOCENTE']);
require_once __DIR__ . '/../config.php';

$pdo = DB::conn();
$u = Session::user();
$idUsuario = (int)$u['id'];
$nombreCompleto = $u['nombre'] ?? 'Usuario';
$uid = (int)($u['id'] ?? 0);

// Foto de perfil
$rutaFoto = 'storage/fotos/doc_' . $uid . '.jpg';
$rutaFisica = __DIR__ . '/../../../' . $rutaFoto;
if (file_exists($rutaFisica)) {
    $imgSrc = '../' . $rutaFoto . '?v=' . time();
} else {
    $imgSrc = '/SIGED/public/img/User.png';
}

$id = (int)($_GET['id'] ?? 0);

// Seguridad + traer responsable y su depto
$sql = "
  SELECT T.*,
         D.ID_DOCENTE,
         URESP.NOMBRE_USUARIO AS JEFE_NOMBRE,
         URESP.ID_USUARIO AS JEFE_ID,
         URESP.CORREO AS JEFE_CORREO,
         DP.NOMBRE_DEPARTAMENTO AS JEFE_DEPTO
  FROM dbo.TICKETS T
  JOIN dbo.DOCENTE D ON D.ID_USUARIO = :u
  LEFT JOIN dbo.USUARIOS URESP ON URESP.ID_USUARIO = T.ID_USUARIO_RESPONSABLE
  LEFT JOIN dbo.DEPARTAMENTO DP ON DP.ID_DEPARTAMENTO = URESP.ID_DEPARTAMENTO
  WHERE T.ID_TICKET = :id
    AND T.ID_DOCENTE_GENERADOR = D.ID_DOCENTE
";
$chk = $pdo->prepare($sql);
$chk->execute([':u'=>$idUsuario, ':id'=>$id]);
$tk = $chk->fetch();

if (!$tk) {
    http_response_code(404);
    echo "Ticket no encontrado";
    exit;
}

$asignadoA = '—';
if (!empty($tk['JEFE_NOMBRE'])) {
    $asignadoA = $tk['JEFE_NOMBRE'] . (!empty($tk['JEFE_DEPTO']) ? ' · ' . $tk['JEFE_DEPTO'] : '');
} elseif (!empty($tk['ID_USUARIO_RESPONSABLE'])) {
    $asignadoA = 'Usuario #'.$tk['ID_USUARIO_RESPONSABLE'];
}

// Pasar datos a JavaScript
$jsData = json_encode([
    'userId' => $uid,
    'nombreCompleto' => $nombreCompleto,
    'ticketId' => $id
]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?= (int)$id ?> | SIGED</title>
    
    <link rel="stylesheet" href="/SIGED/public/css/VerTicket.css">
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
                        <img src="/SIGED/public/img/IconosSIged/Recurso 8Icono_firma.png" alt="Mi Firma" class="nav-img">
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
        <div class="content-wrapper ticket-detail-wrapper">
            
            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <a href="/SIGED/public/index.php?action=tk_list">
                    <i class='bx bx-arrow-back'></i>
                    Volver a Tickets
                </a>
            </div>

            <!-- Encabezado del Ticket -->
            <div class="ticket-header">
                <div class="ticket-header-top">
                    <h1 class="ticket-title">
                        <span class="ticket-id">#<?= (int)$id ?></span>
                        <?= htmlspecialchars($tk['TITULO']) ?>
                    </h1>
                    <span class="badge-status <?= strtolower($tk['ESTATUS']) ?>">
                        <?= htmlspecialchars($tk['ESTATUS']) ?>
                    </span>
                </div>
                
                <div class="ticket-meta">
                    <div class="meta-item">
                        <i class='bx bx-calendar'></i>
                        <span>Creado: <?= substr((string)$tk['FECHA_CREACION'], 0, 16) ?></span>
                    </div>
                    <div class="meta-item">
                        <i class='bx bx-flag'></i>
                        <span>Prioridad: <strong><?= htmlspecialchars($tk['PRIORIDAD']) ?></strong></span>
                    </div>
                    <div class="meta-item">
                        <i class='bx bx-user-check'></i>
                        <span>Asignado a: <strong><?= htmlspecialchars($asignadoA) ?></strong></span>
                    </div>
                    <?php if (!empty($tk['TIPO'])): ?>
                    <div class="meta-item">
                        <i class='bx bx-category'></i>
                        <span>Tipo: <strong><?= htmlspecialchars($tk['TIPO']) ?></strong></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Descripción -->
            <div class="ticket-section">
                <h2 class="section-title">
                    <i class='bx bx-message-square-detail'></i>
                    Descripción
                </h2>
                <div class="ticket-description">
                    <?= nl2br(htmlspecialchars($tk['DESCRIPCION'])) ?>
                </div>
            </div>

            <!-- Comentarios -->
            <div class="ticket-section">
                <h2 class="section-title">
                    <i class='bx bx-conversation'></i>
                    Comentarios
                    <?php
                    $c = $pdo->prepare("SELECT * FROM dbo.TICKET_COMENTARIO WHERE ID_TICKET=:id ORDER BY FECHA DESC");
                    $c->execute([':id'=>$id]);
                    $com = $c->fetchAll();
                    ?>
                    <span class="comment-count">(<?= count($com) ?>)</span>
                </h2>

                <div class="comments-list">
                    <?php if (empty($com)): ?>
                        <div class="no-comments">
                            <i class='bx bx-message-rounded-x'></i>
                            <p>No hay comentarios todavía</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($com as $cm): ?>
                            <div class="comment-item">
                                <div class="comment-header">
                                    <div class="comment-author">
                                        <i class='bx <?= $cm['ID_USUARIO'] ? 'bx-user-circle' : 'bxs-user-badge' ?>'></i>
                                        <strong>
                                            <?= $cm['ID_USUARIO'] ? 'Jefe #'.$cm['ID_USUARIO'] : 'Tú' ?>
                                        </strong>
                                    </div>
                                    <div class="comment-date">
                                        <i class='bx bx-time'></i>
                                        <?= substr((string)$cm['FECHA'], 0, 16) ?>
                                    </div>
                                </div>
                                <div class="comment-body">
                                    <?= nl2br(htmlspecialchars($cm['TEXTO'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Formulario de comentario -->
                <?php if ($tk['ESTATUS'] !== 'CERRADO'): ?>
                    <form action="/SIGED/public/index.php?action=tk_comentar" method="post" class="comment-form">
                        <input type="hidden" name="id" value="<?= (int)$id ?>">
                        <div class="form-group">
                            <label for="texto" class="form-label">
                                <i class='bx bx-edit-alt'></i>
                                Agregar comentario
                            </label>
                            <textarea 
                                name="texto" 
                                id="texto"
                                class="form-textarea" 
                                required 
                                placeholder="Escribe tu comentario para el Jefe de Departamento..."
                                rows="4"
                            ></textarea>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn-submit">
                                <i class='bx bx-send'></i>
                                Enviar comentario
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <!-- Resolución (si está cerrado) -->
            <?php if ($tk['ESTATUS'] === 'CERRADO'): ?>
                <div class="ticket-section resolution-section">
                    <h2 class="section-title">
                        <i class='bx bx-check-circle'></i>
                        Ticket Cerrado
                    </h2>
                    <div class="resolution-info">
                        <div class="meta-item">
                            <i class='bx bx-calendar-check'></i>
                            <span>Fecha de cierre: <?= $tk['FECHA_CIERRE'] ? substr((string)$tk['FECHA_CIERRE'], 0, 16) : '—' ?></span>
                        </div>
                        <?php if (!empty($tk['JEFE_CORREO'])): ?>
                        <div class="meta-item">
                            <i class='bx bx-envelope'></i>
                            <span>Contacto: <a href="mailto:<?= htmlspecialchars($tk['JEFE_CORREO']) ?>"><?= htmlspecialchars($tk['JEFE_CORREO']) ?></a></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($tk['RESOLUCION'])): ?>
                        <div class="resolution-text">
                            <strong>Resolución:</strong>
                            <p><?= nl2br(htmlspecialchars($tk['RESOLUCION'])) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <!-- Scripts -->
    <script>
        window.APP_DATA = <?php echo $jsData; ?>;
    </script>
    <script src="/SIGED/public/js/menu.js"></script>
    <script src="/SIGED/public/js/ver_ticket.js"></script>
</body>
</html>
