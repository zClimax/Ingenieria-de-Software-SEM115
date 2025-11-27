<?php
// filepath: c:\xampp\htdocs\siged\app\php\solicitudes\editar.php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';

Session::start();
requireRole(['DOCENTE']);

$user = Session::user();
$pdo = DB::conn();
$S = Config::MAP['SOLICITUD'];
$E = Config::MAP['EVID'];
$ts = $S['TABLE'];
$sId = $S['ID'];
$sTipo = $S['TIPO'];
$sEstado = $S['ESTADO'];

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    die('ID de solicitud inválido');
}

// Obtener solicitud
$sql = "SELECT $sId AS id, $sTipo AS tipo, $sEstado AS estado FROM $ts WHERE $sId = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $id]);
$sol = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sol) {
    http_response_code(404);
    die('Solicitud no encontrada');
}

// Obtener evidencias
$te = $E['TABLE'];
$eId = $E['ID'];
$eNom = $E['NOM'];
$sqlEv = "SELECT $eId AS id, $eNom AS nombre FROM $te WHERE " . $E['SOL'] . " = :id ORDER BY $eId DESC";
$stmtEv = $pdo->prepare($sqlEv);
$stmtEv->execute([':id' => $id]);
$evidencias = $stmtEv->fetchAll(PDO::FETCH_ASSOC);

// Datos del usuario
$nombreCompleto = $user['nombre'] ?? 'Usuario';
$uid = (int)($user['id'] ?? 0);

// Foto de perfil
$rutaFoto = 'storage/fotos/doc_' . $uid . '.jpg';
$rutaFisica = __DIR__ . '/../../../' . $rutaFoto;
if (file_exists($rutaFisica)) {
    $imgSrc = '../' . $rutaFoto . '?v=' . time();
} else {
    $imgSrc = '';
}

// Determinar badge de estado
function obtenerBadgeEstado($estado) {
    return match(strtoupper($estado)) {
        'BORRADOR' => ['badge-borrador', 'Borrador'],
        'ENVIADA' => ['badge-enviada', 'Enviada'],
        'APROBADA' => ['badge-aprobada', 'Aprobada'],
        'RECHAZADA' => ['badge-rechazada', 'Rechazada'],
        default => ['badge-borrador', $estado]
    };
}

[$badgeClass, $badgeText] = obtenerBadgeEstado($sol['estado']);

// Mensajes de alerta
$msg = $_GET['msg'] ?? '';
function obtenerMensaje($m) {
    return match($m) {
        'subido_ok' => ['alerta-exito', 'Evidencia subida correctamente.'],
        'enviado_ok' => ['alerta-exito', 'Solicitud enviada a validación.'],
        'error_archivo' => ['alerta-error', 'Error al subir el archivo. Verifica el formato y tamaño.'],
        'error_tipo' => ['alerta-error', 'Tipo de archivo no permitido. Solo PDF, JPG, PNG.'],
        'error_peso' => ['alerta-error', 'El archivo es demasiado grande. Máximo 5MB.'],
        default => ['', '']
    };
}
[$alertaClase, $alertaTexto] = obtenerMensaje($msg);

// Pasar datos a JavaScript
$jsData = json_encode([
    'solicitudId' => $id,
    'estado' => $sol['estado']
]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIGED · Editar Solicitud #<?= $id ?></title>
    
    <link rel="stylesheet" href="/SIGED/public/css/EditarSolicitud.css">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>

    <!-- ENCABEZADO -->
    <header class="encabezado">
        <div class="seccion-izquierda">
            <div class="icono-menu" id="menuToggle">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
        <div class="seccion-centro">
            <a href="/SIGED/public/index.php?action=home_docente" title="SIGED - Inicio">
                <img src="/SIGED/public/img/IconosSIged/Recurso 3SIGED_LOGO.png" alt="SIGED Logo" class="logo-encabezado">
            </a>
        </div>
        <div class="seccion-derecha">
            <a href="/SIGED/public/index.php?action=notificaciones" class="enlace-notificacion">
                <i class='bx bx-bell'></i>
            </a>
            <button class="boton-salir" onclick="location.href='/SIGED/public/index.php?action=logout'">Salir</button>
        </div>
    </header>

    <!-- BARRA LATERAL -->
    <aside class="barra-lateral" id="sidebar">
        <nav class="navegacion-lateral">
            <ul>
                <li>
                    <a href="/SIGED/public/index.php?action=home_docente" class="nav-link">
                        <img src="/SIGED/public/img/IconosSIged/Recurso 5Icono_usuario.png" alt="Inicio" class="icono-navegacion">
                        <span class="texto-navegacion">Inicio</span>
                    </a>
                </li>
                <li>
                    <a href="/SIGED/public/index.php?action=sol_mis" class="nav-link active">
                        <img src="/SIGED/public/img/IconosSIged/Recurso 6Icono_GActas.png" alt="Mis Solicitudes" class="icono-navegacion">
                        <span class="texto-navegacion">Mis Solicitudes</span>
                    </a>
                </li>
                <li>
                    <a href="/SIGED/public/index.php?action=doc_firma" class="nav-link">
                        <img src="/SIGED/public/img/IconosSIged/Recurso 6Icono_firma.svg" alt="Mi Firma" class="icono-navegacion">
                        <span class="texto-navegacion">Mi Firma</span>
                    </a>
                </li>
                <li>
                    <a href="/SIGED/public/index.php?action=tk_list" class="nav-link">
                        <img src="/SIGED/public/img/IconosSIged/Recurso 7Icono_tickets.png" alt="Tickets" class="icono-navegacion">
                        <span class="texto-navegacion">Tickets</span>
                    </a>
                </li>
            </ul>
        </nav>
        <div class="pie-barra-lateral">
            <div class="informacion-usuario">
                <div class="avatar-usuario">
                    <?php if (!empty($imgSrc)): ?>
                        <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="Usuario" class="imagen-avatar-usuario">
                    <?php else: ?>
                        <i class='bx bx-user'></i>
                    <?php endif; ?>
                </div>
                <div class="detalles-usuario">
                    <div class="nombre-usuario"><?= htmlspecialchars($nombreCompleto) ?></div>
                </div>
            </div>
        </div>
    </aside>

    <!-- CONTENIDO PRINCIPAL -->
    <main class="contenido-principal">
        <div class="contenedor-contenido">

            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <a href="/SIGED/public/index.php?action=sol_mis">
                    <i class='bx bx-arrow-back'></i> Mis Solicitudes
                </a>
                <span>/</span>
                <span>Editar #<?= $id ?></span>
            </div>

            <!-- Alerta de mensajes -->
            <?php if ($alertaTexto): ?>
                <div class="alerta <?= $alertaClase ?>">
                    <i class='bx <?= $alertaClase === 'alerta-exito' ? 'bx-check-circle' : 'bx-error-circle' ?>'></i>
                    <span><?= htmlspecialchars($alertaTexto) ?></span>
                </div>
            <?php endif; ?>

            <!-- Tarjeta de Información de la Solicitud -->
            <section class="tarjeta-solicitud">
                <div class="cabecera-solicitud">
                    <div>
                        <h2 class="titulo-solicitud">Solicitud #<?= $id ?></h2>
                        <p class="subtitulo-solicitud">Tipo: <strong><?= htmlspecialchars($sol['tipo']) ?></strong></p>
                    </div>
                    <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($badgeText) ?></span>
                </div>
            </section>

            <!-- Tarjeta de Subir Evidencias (Solo si es BORRADOR) -->
            <?php if (strtoupper($sol['estado']) === 'BORRADOR'): ?>
            <section class="tarjeta-formulario">
                <h3 class="titulo-seccion">
                    <i class='bx bx-upload'></i> Subir Evidencias
                </h3>
                <form method="post" enctype="multipart/form-data" 
                      action="/SIGED/public/index.php?action=sol_subir" 
                      class="formulario-subir" id="formSubir">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <div class="grupo-campo">
                        <label for="archivo">Seleccionar archivo</label>
                        <div class="contenedor-archivo">
                            <input type="file" name="archivo" id="archivo" class="input-archivo" 
                                   accept=".pdf,.jpg,.jpeg,.png" required>
                            <label for="archivo" class="etiqueta-archivo">
                                <i class='bx bx-cloud-upload'></i>
                                <span>Seleccionar archivo</span>
                            </label>
                        </div>
                        <small class="texto-ayuda">
                            Permitidos: PDF, JPG, PNG · Máximo 5 MB
                        </small>
                    </div>
                    <div class="grupo-botones">
                        <button type="submit" class="boton-primario">
                            <i class='bx bx-upload'></i>
                            Subir Archivo
                        </button>
                    </div>
                </form>
            </section>

            <!-- Botón Enviar a Validación -->
            <section class="tarjeta-enviar">
                <div class="contenido-enviar">
                    <div>
                        <h3 class="titulo-enviar">¿Listo para validación?</h3>
                        <p class="texto-enviar">Una vez enviada, no podrás modificar la solicitud hasta que sea revisada.</p>
                    </div>
                    <form method="post" action="/SIGED/public/index.php?action=sol_enviar" 
                          onsubmit="return confirm('¿Estás seguro de enviar la solicitud a validación?');">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <button type="submit" class="boton-enviar">
                            <i class='bx bx-paper-plane'></i>
                            Enviar a Validación
                        </button>
                    </form>
                </div>
            </section>
            <?php endif; ?>

            <!-- Tarjeta de Evidencias -->
            <section class="tarjeta-evidencias">
                <h3 class="titulo-seccion">
                    <i class='bx bx-file'></i> Evidencias Adjuntas
                </h3>
                
                <?php if (empty($evidencias)): ?>
                    <div class="contenido-vacio">
                        <i class='bx bx-folder-open'></i>
                        <p>No hay evidencias cargadas aún.</p>
                        <?php if (strtoupper($sol['estado']) === 'BORRADOR'): ?>
                            <small>Sube al menos una evidencia antes de enviar a validación.</small>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="lista-evidencias">
                        <?php foreach ($evidencias as $ev): ?>
                            <div class="item-evidencia">
                                <div class="info-evidencia">
                                    <i class='bx bxs-file-pdf'></i>
                                    <span class="nombre-evidencia"><?= htmlspecialchars($ev['nombre']) ?></span>
                                </div>
                                <div class="acciones-evidencia">
                                    <a href="/SIGED/public/index.php?action=sol_descargar&id=<?= (int)$ev['id'] ?>" 
                                       class="boton-icono boton-descargar" title="Descargar">
                                        <i class='bx bx-download'></i>
                                    </a>
                                    <?php if (strtoupper($sol['estado']) === 'BORRADOR'): ?>
                                        <button class="boton-icono boton-eliminar" 
                                                data-id="<?= (int)$ev['id'] ?>" 
                                                title="Eliminar">
                                            <i class='bx bx-trash'></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

        </div>
    </main>

    <!-- Scripts -->
    <script>
        window.APP_DATA = <?= $jsData ?>;
    </script>
    <script src="/SIGED/public/js/menu.js"></script>
    <script src="/SIGED/public/js/editar_solicitud.js"></script>
</body>
</html>
