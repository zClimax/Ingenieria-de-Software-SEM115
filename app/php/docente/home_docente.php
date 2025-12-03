<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../utils/roles.php';
require_once __DIR__ . '/../config.php';

Session::start();
requireRole(['DOCENTE']);

$user = Session::user();
$pdo  = DB::conn();
$D    = Config::MAP['DOCENTE'];

// MODIFICACIÓN: Hacemos JOIN con USUARIOS y DEPARTAMENTO para obtener el nombre real
$sql = "
    SELECT D.*, DP.NOMBRE_DEPARTAMENTO
    FROM {$D['TABLE']} D
    INNER JOIN dbo.USUARIOS U ON U.ID_USUARIO = D.ID_USUARIO
    LEFT JOIN dbo.DEPARTAMENTO DP ON DP.ID_DEPARTAMENTO = U.ID_DEPARTAMENTO
    WHERE D.ID_USUARIO = :id_usr
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':id_usr' => $user['id']]);
$docenteData = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$nombreCompleto = $user['nombre']; 
$correo         = $user['correo']; 
$departamento   = $docenteData['NOMBRE_DEPARTAMENTO'] ?? 'Sin departamento asignado';

// Mapeo de campos
$rfc            = $docenteData[$D['RFC']]       ?? '—';// filepath: c:\xampp\htdocs\SIGED\app\php\docente\home_docente.php
$curp           = $docenteData[$D['CURP']]      ?? '—';
$telefono       = $docenteData[$D['TEL']]       ?? '—';
$claveEmpleado  = $docenteData[$D['CLAVE_EMPLEADO'] ?? 'CLAVE_EMPLEADO'] ?? '—';
$matricula      = $docenteData[$D['MATRICULA'] ?? 'MATRICULA'] ?? '—';
$nss            = $docenteData[$D['NSS'] ?? 'NSS']       ?? '—';
$gradodeEstudios= $docenteData[$D['GRADO_ESTUDIOS'] ?? 'GRADO_ESTUDIOS'] ?? '—';
$fechaIngreso   = $docenteData[$D['FECHA_INGRESO'] ?? 'FECHA_INGRESO']  ?? '—'; 

if ($fechaIngreso !== '—') {
    try { $fechaIngreso = (new DateTime($fechaIngreso))->format('d/m/Y'); } catch(Exception $e){}
}

$rutaFotoRelativa = 'storage/fotos/doc_' . $user['id'] . '.jpg';
$rutaFisica       = __DIR__ . '/../../../' . $rutaFotoRelativa; 
$imgSrc = file_exists($rutaFisica) ? '../' . $rutaFotoRelativa . '?v=' . time() : '/SIGED/public/img/User.png';

// Pasar datos a JavaScript
$jsData = json_encode([
    'userId' => $user['id'],
    'nombreCompleto' => $nombreCompleto
]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    
    <link rel="stylesheet" href="/SIGED/public/css/InterfazMenuPrincipal.css">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    
    <title>SIGED - Inicio Docente</title>
</head>
<body>

    <!-- Modal Convocatoria Activa -->
    <div id="convModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" id="closeConv">&times;</span>
            <div class="modal-header">
                <h2 class="modal-title">Convocatoria Activa</h2>
            </div>
            <div class="modal-body">
                <div id="convMeta" class="modal-subtitle"></div>
                <strong>Requisitos:</strong>
                <ul id="reqList" style="margin: 10px 0; padding-left: 20px;"></ul>
                <div id="convMsg" class="modal-message" style="margin-top: 15px; color: #666;"></div>
            </div>
            <div class="modal-footer">
                <button id="btnConvOk" class="btn-modal">Entendido</button>
            </div>
        </div>
    </div>

    <!-- Modal Histórico de Convocatorias -->
    <div id="modalHistorico" class="modal">
        <div class="modal-content modal-large">
            <span class="close-modal" id="closeHist">&times;</span>
            <div class="modal-header">
                <h2 class="modal-title">Histórico de Convocatorias</h2>
            </div>
            <div class="modal-body">
                
                <!-- Vista Resumen -->
                <div id="histViewResumen">
                    <div class="table-responsive">
                        <table class="tabla-historico">
                            <thead>
                                <tr>
                                    <th>Año</th>
                                    <th>Convocatoria</th>
                                    <th>Vigencia</th>
                                    <th>Puntos</th>
                                    <th>%</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody id="tbHistResumen"></tbody>
                        </table>
                    </div>
                </div>

                <!-- Vista Detalle -->
                <div id="histViewDetalle" style="display:none;">
                    <div style="margin-bottom:15px; border-bottom:1px solid #eee; padding-bottom:10px;">
                        <button class="btn-outline-small" id="btnVolverHist">← Volver</button>
                        <h3 id="detTitulo" style="margin:10px 0 5px; color:var(--accent);"></h3>
                        <span id="detVigencia" style="font-size:0.9rem; color:#666;"></span>
                    </div>
                    <div class="table-responsive">
                        <table class="tabla-historico">
                            <thead>
                                <tr>
                                    <th>Evidencia</th>
                                    <th>Puntos</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody id="tbHistDetalle"></tbody>
                        </table>
                    </div>
                </div>

                <!-- Loading -->
                <div id="histLoading" style="text-align:center; display:none; padding:20px;">
                    <i class='bx bx-loader-alt bx-spin' style="font-size:2rem; color:var(--accent);"></i>
                </div>
            </div>
        </div>
    </div>

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
                    <a href="/SIGED/public/index.php?action=home_docente" class="nav-link active">
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
                    <a href="/SIGED/public/index.php?action=tk_list" class="nav-link">
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
        <div class="content-wrapper">
            
            <!-- Anuncio Cédula Profesional -->
            <div class="cedula-banner">
                <div class="cedula-icon-box">
                    <i class='bx bxs-certification'></i>
                </div>
                <div class="cedula-content">
                    <h3>Trámite de Cédula Profesional</h3>
                    <p>Recuerda que es indispensable contar con tu Cédula Profesional actualizada para el ejercicio docente. Realiza tu trámite en línea.</p>
                </div>
                <a href="https://www.gob.mx/cedulaprofesional" target="_blank" class="cedula-btn">
                    Sitio Oficial <i class='bx bx-link-external'></i>
                </a>
            </div>

            <!-- Identificación -->
            <section class="identificacion">
                <h2>Identificación</h2>
                <div class="info-card">
                    <div class="avatar-container" id="avatarContainer" title="Cambiar foto">
                        <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="Usuario" class="avatar-img-fit">
                        <div class="avatar-overlay">
                            <i class='bx bx-camera'></i>
                        </div>
                    </div>
                    <input type="file" id="fileInput" name="foto" accept="image/*" style="display:none;">
                    <div class="info-details">
                        <h3><?php echo htmlspecialchars($nombreCompleto); ?></h3>
                        <p><strong>Correo:</strong> <?php echo htmlspecialchars($correo); ?></p>
                        
                        <p class="texto-departamento"><?php echo htmlspecialchars($departamento); ?></p>
                    </div>
                </div>
            </section>

            <!-- Datos Personales -->
            <section class="datos-personales">
                <h2>Datos personales</h2>
                <div class="datos-grid">
                    <p><strong>RFC:</strong> <?php echo htmlspecialchars($rfc); ?></p>
                    <p><strong>CURP:</strong> <?php echo htmlspecialchars($curp); ?></p>
                    <p><strong>TELÉFONO:</strong> <?php echo htmlspecialchars($telefono); ?></p>
                    <p><strong>MATRÍCULA:</strong> <?php echo htmlspecialchars($matricula); ?></p>
                    <p><strong>CLAVE:</strong> <?php echo htmlspecialchars($claveEmpleado); ?></p>
                    <p><strong>NSS:</strong> <?php echo htmlspecialchars($nss); ?></p>
                    <p><strong>GRADO:</strong> <?php echo htmlspecialchars($gradodeEstudios); ?></p>
                    <p><strong>INGRESO:</strong> <?php echo htmlspecialchars($fechaIngreso); ?></p>
                </div>
            </section>

            <!-- Progreso de Convocatoria -->
            <section class="progress-card">
                <div class="progress-header-title">
                    <h2>Progreso de Convocatoria</h2>
                </div>
                <div class="progress-stats">
                    <span>Avance</span>
                    <div>
                        <span id="ptsLabel">0 pts.</span> / <span id="pctLabel">0%</span>
                    </div>
                </div>
                <div class="bar-wrap">
                    <div class="bar" id="bar"></div>
                </div>

                <!-- Aviso de puntaje no oficial -->
                <div class="aviso-puntaje">
                    <i class='bx bx-info-circle'></i>
                    <span><strong>Importante:</strong> Este puntaje es una estimación preliminar y no representa el resultado oficial de la evaluación.</span>
                </div>
            </section>

            <!-- Botón Histórico -->
            <div class="historico-btn">
                <button class="btn-outline" id="btnAbrirHistorico">
                    Histórico de convocatorias
                </button>
            </div>
        </div>
    </main>

    <!-- Scripts -->
    <script>
        // Pasar datos PHP a JavaScript
        window.APP_DATA = <?php echo $jsData; ?>;
    </script>
    <script src="/SIGED/public/js/menu.js"></script>
    <script src="/SIGED/public/js/home_docente.js"></script>
</body>
</html>