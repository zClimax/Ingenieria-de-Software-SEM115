<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php'; 
Session::start();
require_once __DIR__ . '/../utils/roles.php'; 
requireRole(['JEFE_DEPARTAMENTO']);
require_once __DIR__ . '/../config.php';

$pdo = DB::conn(); 
$u = Session::user(); 
$idJefe = (int)$u['id'];
$id = (int)($_GET['id'] ?? 0);

// Foto de perfil
$rutaFoto = 'storage/fotos/jefe_' . $idJefe . '.jpg';
$rutaFisica = __DIR__ . '/../../../' . $rutaFoto;
if (file_exists($rutaFisica)) {
    $imgSrc = '../' . $rutaFoto . '?v=' . time();
} else {
    $imgSrc = '/SIGED/public/img/User.png';
}

$displayName = trim((string)($u['nombre'] ?? 'Jefe de Departamento'));

// Seguridad: debe estar asignado a este jefe
$chk = $pdo->prepare("SELECT T.*
  , CONCAT(D.NOMBRE_DOCENTE,' ',D.APELLIDO_PATERNO_DOCENTE,' ',D.APELLIDO_MATERNO_DOCENTE) AS DOCENTE
  , D.CORREO
  FROM dbo.TICKETS T
  JOIN dbo.DOCENTE D ON D.ID_DOCENTE=T.ID_DOCENTE_GENERADOR
  WHERE T.ID_TICKET=:id AND T.ID_USUARIO_RESPONSABLE=:j");
$chk->execute([':id' => $id, ':j' => $idJefe]); 
$tk = $chk->fetch();

if (!$tk) { 
    http_response_code(404); 
    echo "Ticket no asignado a usted o inexistente."; 
    exit; 
}

// Flag de solo lectura si está cerrado
$isCerrado = strtoupper((string)$tk['ESTATUS']) === 'CERRADO';

// Comentarios
$c = $pdo->prepare("SELECT * FROM dbo.TICKET_COMENTARIO WHERE ID_TICKET=:id ORDER BY FECHA DESC");
$c->execute([':id' => $id]); 
$com = $c->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?= (int)$id ?> | SIGED</title>
    
    <link rel="stylesheet" href="/SIGED/public/css/TicketVer.css">
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
        <div class="ticket-wrapper">

            <!-- Header del ticket -->
            <section class="ticket-header">
                <div class="breadcrumb">
                    <a href="/SIGED/public/index.php?action=tkj_list">
                        <i class='bx bx-support'></i>
                        Tickets
                    </a>
                    <i class='bx bx-chevron-right'></i>
                    <span>Detalle del ticket</span>
                </div>

                <h1 class="ticket-title">
                    <span class="ticket-id">#<?= (int)$id ?></span>
                    <?= htmlspecialchars($tk['TITULO']) ?>
                </h1>

                <div class="ticket-meta">
                    <div class="meta-item">
                        <span class="meta-label">Estado</span>
                        <span class="meta-value">
                            <?php
                            $estadoClass = match(strtoupper($tk['ESTATUS'])) {
                                'ABIERTO' => 'abierto',
                                'EN_REVISION' => 'revision',
                                'CERRADO' => 'cerrado',
                                default => 'abierto'
                            };
                            ?>
                            <span class="status-badge <?= $estadoClass ?>">
                                <i class='bx <?= $estadoClass === 'cerrado' ? 'bx-check-circle' : ($estadoClass === 'revision' ? 'bx-time-five' : 'bx-folder-open') ?>'></i>
                                <?= htmlspecialchars($tk['ESTATUS']) ?>
                            </span>
                        </span>
                    </div>

                    <div class="meta-item">
                        <span class="meta-label">Prioridad</span>
                        <span class="meta-value">
                            <?php
                            $prioClass = match(strtoupper($tk['PRIORIDAD'])) {
                                'ALTA' => 'alta',
                                'MEDIA' => 'media',
                                'BAJA' => 'baja',
                                default => 'baja'
                            };
                            ?>
                            <span class="priority-badge <?= $prioClass ?>">
                                <i class='bx bx-error-circle'></i>
                                <?= htmlspecialchars($tk['PRIORIDAD']) ?>
                            </span>
                        </span>
                    </div>

                    <div class="meta-item">
                        <span class="meta-label">Docente</span>
                        <span class="meta-value">
                            <i class='bx bx-user'></i>
                            <?= htmlspecialchars($tk['DOCENTE']) ?>
                        </span>
                    </div>

                    <div class="meta-item">
                        <span class="meta-label">Correo</span>
                        <span class="meta-value">
                            <i class='bx bx-envelope'></i>
                            <?= htmlspecialchars($tk['CORREO']) ?>
                        </span>
                    </div>

                    <div class="meta-item">
                        <span class="meta-label">Fecha de creación</span>
                        <span class="meta-value">
                            <i class='bx bx-calendar'></i>
                            <?= substr((string)$tk['FECHA_CREACION'], 0, 16) ?>
                        </span>
                    </div>
                </div>
            </section>

            <!-- Grid de contenido -->
            <div class="content-grid">
                <!-- Columna izquierda: Comentarios -->
                <div>
                    <section class="comments-section">
                        <h3 class="section-title">
                            <i class='bx bx-conversation'></i>
                            Comentarios
                        </h3>

                        <?php if (count($com) > 0): ?>
                            <div class="comments-list">
                                <?php foreach($com as $cm): ?>
                                    <div class="comment-item">
                                        <div class="comment-header">
                                            <span class="comment-author">
                                                <i class='bx <?= $cm['ID_USUARIO'] ? 'bx-shield-alt-2' : 'bx-user' ?>'></i>
                                                <?= $cm['ID_USUARIO'] ? 'Jefe #'.$cm['ID_USUARIO'] : 'Docente #'.$cm['ID_DOCENTE'] ?>
                                            </span>
                                            <span class="comment-date">
                                                <?= substr((string)$cm['FECHA'], 0, 16) ?>
                                            </span>
                                        </div>
                                        <div class="comment-text">
                                            <?= nl2br(htmlspecialchars($cm['TEXTO'])) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-comments">
                                <i class='bx bx-message-square-dots'></i>
                                <p>No hay comentarios aún</p>
                            </div>
                        <?php endif; ?>

                        <?php if (!$isCerrado): ?>
                            <form action="/SIGED/public/index.php?action=tkj_comentar" method="post" class="comment-form">
                                <input type="hidden" name="id" value="<?= (int)$id ?>">
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class='bx bx-message-square-add'></i>
                                        Nuevo comentario (visible para el docente)
                                    </label>
                                    <textarea name="texto" class="form-textarea" placeholder="Escribe tu comentario aquí..." required></textarea>
                                </div>
                                <button class="btn-primary" type="submit">
                                    <i class='bx bx-send'></i>
                                    Enviar comentario
                                </button>
                            </form>
                        <?php else: ?>
                            <p class="read-only-note">
                                <i class='bx bx-lock'></i>
                                Este ticket está cerrado. No es posible agregar nuevos comentarios.
                            </p>
                        <?php endif; ?>
                    </section>
                </div>

                <!-- Columna derecha: Acciones -->
                <div>
                    <section class="actions-section">
                        <h3 class="section-title">
                            <i class='bx bx-cog'></i>
                            Acciones
                        </h3>

                        <?php if (!$isCerrado): ?>
                            <form action="/SIGED/public/index.php?action=tkj_estado" method="post" class="actions-form">
                                <input type="hidden" name="id" value="<?= (int)$id ?>">
                                
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class='bx bx-info-circle'></i>
                                        Estatus
                                    </label>
                                    <select name="estatus" class="form-select">
                                        <option value="ABIERTO" <?= $tk['ESTATUS']==='ABIERTO'?'selected':'' ?>>ABIERTO</option>
                                        <option value="EN_REVISION" <?= $tk['ESTATUS']==='EN_REVISION'?'selected':'' ?>>EN REVISIÓN</option>
                                        <option value="CERRADO" <?= $tk['ESTATUS']==='CERRADO'?'selected':'' ?>>CERRADO</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">
                                        <i class='bx bx-error-circle'></i>
                                        Prioridad
                                    </label>
                                    <select name="prioridad" class="form-select">
                                        <option value="BAJA" <?= $tk['PRIORIDAD']==='BAJA'?'selected':'' ?>>BAJA</option>
                                        <option value="MEDIA" <?= $tk['PRIORIDAD']==='MEDIA'?'selected':'' ?>>MEDIA</option>
                                        <option value="ALTA" <?= $tk['PRIORIDAD']==='ALTA'?'selected':'' ?>>ALTA</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">
                                        <i class='bx bx-note'></i>
                                        Nota de resolución (si cierra)
                                    </label>
                                    <textarea name="resolucion" class="form-textarea" placeholder="Resumen de la solución/aprobación..."></textarea>
                                </div>

                                <button class="btn-success" type="submit">
                                    <i class='bx bx-save'></i>
                                    Aplicar cambios
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="read-only-box">
                                <p>
                                    <i class='bx bx-lock-alt'></i>
                                    Este ticket está <strong>CERRADO</strong>. Los cambios de estatus, prioridad y resolución ya no están disponibles.
                                </p>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>
            </div>

            <!-- Navegación -->
            <div class="back-section">
                <a href="/SIGED/public/index.php?action=tkj_list" class="btn-back">
                    <i class='bx bx-arrow-back'></i>
                    Volver a Tickets
                </a>
            </div>

        </div>
    </main>

    <!-- Scripts -->
    <script src="/SIGED/public/js/menu.js"></script>
    <script src="/SIGED/public/js/ticket_ver.js"></script>
</body>
</html>
