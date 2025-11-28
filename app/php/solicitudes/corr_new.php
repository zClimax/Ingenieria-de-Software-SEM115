<?php
// filepath: c:\xampp\htdocs\SIGED\app\php\solicitudes\corr_new.php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';

requireRole(['DOCENTE']);
Session::start();

$pdo = DB::conn();
$u   = Session::user();

// 1) Parámetros
$idSol = (int)($_GET['id'] ?? 0);
if ($idSol <= 0) { http_response_code(400); exit('ID inválido'); }

$idUsuario = (int)($u['id'] ?? 0);
if ($idUsuario <= 0) { http_response_code(403); exit('Sesión inválida'); }

// 2) Resolver ID_DOCENTE del usuario actual
$sqlDoc = $pdo->prepare("
  SELECT D.ID_DOCENTE
  FROM dbo.DOCENTE D
  JOIN dbo.USUARIOS U ON U.ID_USUARIO = D.ID_USUARIO
  WHERE U.ID_USUARIO = :idu
");
$sqlDoc->execute([':idu' => $idUsuario]);
$idDocente = (int)($sqlDoc->fetchColumn() ?: 0);
if ($idDocente <= 0) { http_response_code(403); exit('Docente no encontrado'); }

// 3) Cargar solicitud
$sqlSol = $pdo->prepare("
  SELECT ID_SOLICITUD, ID_DOCENTE, TIPO_DOCUMENTO, ESTADO, ID_DEPARTAMENTO_APROBADOR
  FROM dbo.SOLICITUD_DOCUMENTO
  WHERE ID_SOLICITUD = :id
");
$sqlSol->execute([':id' => $idSol]);
$sol = $sqlSol->fetch(PDO::FETCH_ASSOC);
if (!$sol) { http_response_code(404); exit('Solicitud no encontrada'); }

// 4) Validaciones de propiedad y estado
if ((int)$sol['ID_DOCENTE'] !== $idDocente) {
  http_response_code(403); exit('No autorizado');
}
if (($sol['ESTADO'] ?? '') !== 'APROBADA') {
  http_response_code(409); exit('Sólo se puede solicitar corrección para documentos APROBADOS.');
}

// 5) Evitar duplicados
$chk = $pdo->prepare("
  SELECT 1
  FROM dbo.DOC_CORRECCION
  WHERE ID_SOLICITUD = :id
    AND ESTATUS IN ('ABIERTA','EN_EDICION')
");
$chk->execute([':id' => $idSol]);
if ($chk->fetchColumn()) {
  http_response_code(409); exit('Ya existe una corrección abierta para esta solicitud.');
}

// Datos del usuario
$nombreCompleto = $u['nombre'] ?? 'Usuario';
$uid = (int)($u['id'] ?? 0);

// Foto de perfil
$rutaFoto = 'storage/fotos/doc_' . $uid . '.jpg';
$rutaFisica = __DIR__ . '/../../../' . $rutaFoto;
if (file_exists($rutaFisica)) {
    $imgSrc = '../' . $rutaFoto . '?v=' . time();
} else {
    $imgSrc = '';
}

// Pasar datos a JavaScript
$jsData = json_encode([
    'solicitudId' => $idSol,
    'tipoDocumento' => $sol['TIPO_DOCUMENTO'] ?? ''
]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIGED · Solicitar Corrección #<?= $idSol ?></title>
    
    <link rel="stylesheet" href="/SIGED/public/css/SolicitarCorreccion.css">
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
                        <img src="/SIGED/public/img/IconosSIged/Recurso 8Icono_firma.svg" alt="Mi Firma" class="icono-navegacion">
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
                <a href="/SIGED/public/index.php?action=mis_solicitudes">
                    <i class='bx bx-arrow-back'></i> Mis Solicitudes
                </a>
                <span>/</span>
                <span>Solicitar Corrección</span>
            </div>

            <!-- Tarjeta de Información -->
            <section class="tarjeta-informacion">
                <div class="icono-informacion">
                    <i class='bx bx-info-circle'></i>
                </div>
                <div class="texto-informacion">
                    <h3>¿Por qué solicitar una corrección?</h3>
                    <p>Si detectaste algún error en el documento aprobado, puedes solicitar una corrección. 
                       El documento volverá a estado de edición para que puedas realizar los cambios necesarios.</p>
                </div>
            </section>

            <!-- Tarjeta del Formulario -->
            <section class="tarjeta-formulario">
                <div class="cabecera-formulario">
                    <h2 class="titulo-formulario">
                        <i class='bx bx-edit'></i>
                        Solicitar Corrección
                    </h2>
                    <div class="info-solicitud">
                        <span class="badge-solicitud">Solicitud #<?= $idSol ?></span>
                        <span class="tipo-documento"><?= htmlspecialchars($sol['TIPO_DOCUMENTO'] ?? '') ?></span>
                    </div>
                </div>

                <form method="post" action="/SIGED/public/index.php?action=sol_corr_guardar" 
                      class="formulario-correccion" id="formCorreccion">
                    <input type="hidden" name="id" value="<?= $idSol ?>">
                    
                    <div class="grupo-campo">
                        <label for="motivo" class="etiqueta-campo">
                            <i class='bx bx-message-detail'></i>
                            Motivo de la corrección
                        </label>
                        <textarea 
                            id="motivo" 
                            name="motivo" 
                            class="campo-texto" 
                            rows="6" 
                            placeholder="Describe detalladamente qué necesita ser corregido..."
                            required
                            minlength="10"
                            maxlength="500"></textarea>
                        <div class="contador-caracteres">
                            <span id="contadorActual">0</span> / 500 caracteres
                        </div>
                        <small class="texto-ayuda">
                            <i class='bx bx-bulb'></i>
                            Sé específico: menciona qué información debe modificarse y por qué.
                        </small>
                    </div>

                    <!-- Advertencia -->
                    <div class="alerta-advertencia">
                        <i class='bx bx-error'></i>
                        <div>
                            <strong>Importante:</strong>
                            <p>Al enviar esta solicitud, el documento volverá a estado "En edición" y 
                               deberás realizar las correcciones necesarias antes de enviarlo nuevamente.</p>
                        </div>
                    </div>

                    <div class="grupo-botones">
                        <button type="submit" class="boton-primario">
                            <i class='bx bx-send'></i>
                            Enviar Solicitud
                        </button>
                        <a href="/SIGED/public/index.php?action=mis_solicitudes" class="boton-secundario">
                            <i class='bx bx-x'></i>
                            Cancelar
                        </a>
                    </div>
                </form>
            </section>

        </div>
    </main>

    <!-- Scripts -->
    <script>
        window.APP_DATA = <?= $jsData ?>;
    </script>
    <script src="/SIGED/public/js/menu.js"></script>
    <script src="/SIGED/public/js/solicitar_correccion.js"></script>
</body>
</html>
