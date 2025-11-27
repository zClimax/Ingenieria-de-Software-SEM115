<?php
declare(strict_types=1);

// 1. DEPENDENCIAS
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';

// 2. SEGURIDAD
Session::start();
requireRole(['DOCENTE']);

// 3. VARIABLES DE ENTORNO (Aquí corregimos los errores)
$action = $_GET['action'] ?? 'doc_firma'; // Define la acción para el menú
$user   = Session::user();
$pdo    = DB::conn();
$uId    = (int)($user['id'] ?? 0);

// 4. OBTENER DATOS DEL USUARIO
// Usamos $nombreCompleto para que coincida con la variable del menú
$nombreCompleto = trim((string)($user['nombre'] ?? 'Docente')); 
$firmaUrl = '';

if ($uId > 0) {
    // Buscamos nombre completo y firma en la BD
    $st = $pdo->prepare("SELECT NOMBRE_COMPLETO, RUTA_FIRMA FROM dbo.USUARIOS WHERE ID_USUARIO = :id");
    $st->execute([':id' => $uId]);
    if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        if (!empty($row['NOMBRE_COMPLETO'])) { 
            $nombreCompleto = $row['NOMBRE_COMPLETO']; 
        }
        $firmaUrl = (string)($row['RUTA_FIRMA'] ?? '');
    }
}

// Foto de Perfil (Lógica estándar)
$rutaFoto = 'storage/fotos/doc_' . $uId . '.jpg';
$rutaFisica = __DIR__.'/../../../'.$rutaFoto;
$imgSrc = file_exists($rutaFisica) ? '../'.$rutaFoto.'?v='.time() : '/SIGED/public/img/User.png';

// 5. PROCESAR MENSAJES DE ALERTA
$msg = $_GET['msg'] ?? '';
function getMensaje($m) {
    return match($m) {
        'firma_ok'     => 'Firma actualizada correctamente.',
        'firma_tipo'   => 'Formato no válido (usa PNG o JPG).',
        'firma_pesada' => 'El archivo es demasiado grande (Máx 2MB).',
        'firma_error'  => 'Error al subir el archivo.',
        default        => ''
    };
}
$textoMensaje = getMensaje($msg);
$tipoAlerta = ($msg === 'firma_ok') ? 'alerta-exito' : 'alerta-error';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    
    <title>Mi Firma - SIGED</title>

    <link rel="stylesheet" href="/SIGED/public/css/InterfazMenuPrincipal.css">
    <link rel="stylesheet" href="/SIGED/public/css/Firma.css">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
</head>
<body>

    <header class="header">
        <div class="header-left">
            <div class="menu-container" id="menuToggle">
                <div class="menu-icon"><span></span><span></span><span></span></div>
            </div>
        </div>
        <div class="header-center">
            <a href="/SIGED/public/index.php?action=home_docente" title="SIGED - Inicio">
                <img src="/SIGED/public/img/IconosSIged/Recurso%203SIGED_LOGO.png" alt="SIGED" class="site-logo">
            </a>
        </div>
        <div class="header-right">
            <!-- <a href="#" title="Notificaciones"><i class='bx bx-bell'></i></a> -->
            <button class="btn-salir" onclick="window.location.href='/SIGED/public/index.php?action=logout'">Salir</button>
        </div>
    </header>

    <aside class="sidebar" id="sidebar">
        <nav class="sidebar-nav">
            <ul>
                <li>
                    <a href="/SIGED/public/index.php?action=home_docente" class="nav-link <?php echo ($action === 'home_docente') ? 'active' : ''; ?>">
                        <img src="/SIGED/public/img/IconosSIged/Recurso 5Icono_usuario.png" alt="Inicio" class="nav-img">
                        <span class="nav-text">Inicio</span>
                    </a>
                </li>
                <li>
                    <a href="/SIGED/public/index.php?action=sol_mis" class="nav-link <?php echo ($action === 'sol_mis') ? 'active' : ''; ?>">
                        <img src="/SIGED/public/img/IconosSIged/Recurso 6Icono_GActas.png" alt="Mis Solicitudes" class="nav-img">
                        <span class="nav-text">Mis Solicitudes</span>
                    </a>
                </li>
                <li>
                    <a href="/SIGED/public/index.php?action=doc_firma" class="nav-link active"> 
                        <img src="/SIGED/public/img/IconosSIged/Recurso 6Icono_firma.svg" alt="Mi Firma" class="nav-img">
                        <span class="nav-text">Mi Firma</span>
                    </a>
                </li>
                <li>
                    <a href="/SIGED/public/index.php?action=tk_list" class="nav-link <?php echo ($action === 'tk_list') ? 'active' : ''; ?>">
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

    <main class="main-content" id="mainContent">
        <div class="content-wrapper">
            
            <section class="tarjeta-formulario">
                <div style="border-bottom: 1px solid #eee; margin-bottom: 20px; padding-bottom: 10px;">
                    <h2 style="color:var(--text-dark); font-size:1.5rem; margin:0;">Firma Digital</h2>
                    <p style="color:#666; font-size:0.95rem; margin-top:5px;">
                        Esta firma se utilizará para sellar digitalmente tus solicitudes. Asegúrate de usar una imagen clara con fondo transparente.
                    </p>
                </div>

                <?php if ($textoMensaje): ?>
                    <div class="alerta <?php echo $tipoAlerta; ?>" style="padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-weight: bold; <?php echo ($msg === 'firma_ok') ? 'background:#d1fae5; color:#065f46;' : 'background:#fee2e2; color:#991b1b;'; ?>">
                        <i class='bx <?php echo ($msg === 'firma_ok') ? 'bx-check-circle' : 'bx-error-circle'; ?>' style="font-size: 1.2rem;"></i>
                        <span><?php echo htmlspecialchars($textoMensaje); ?></span>
                    </div>
                <?php endif; ?>
                
                <form action="/SIGED/public/index.php?action=doc_firma_guardar" method="POST" enctype="multipart/form-data">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
                        
                        <div class="grupo-campo">
                            <label style="display:block; font-weight:bold; color:var(--text-dark); margin-bottom:8px;">Firma Actual</label>
                            <div style="border: 2px dashed #cbd5e1; border-radius: 12px; height: 180px; display: flex; align-items: center; justify-content: center; background: #f8fafc; overflow: hidden;">
                                <?php if ($firmaUrl): ?>
                                    <img src="/SIGED/<?php echo htmlspecialchars($firmaUrl); ?>?v=<?php echo time(); ?>" alt="Firma" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                                <?php else: ?>
                                    <div style="text-align: center; color: #94a3b8;">
                                        <i class='bx bx-image-add' style="font-size: 3rem;"></i>
                                        <p>Sin firma cargada</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="grupo-campo">
                            <label style="display:block; font-weight:bold; color:var(--text-dark); margin-bottom:8px;">Subir Nueva Firma</label>
                            <div class="contenedor-archivo" style="position: relative; height: 180px;">
                                <input type="file" name="firma" id="archivoFirma" accept="image/png, image/jpeg" required 
                                       style="opacity: 0; width: 100%; height: 100%; position: absolute; cursor: pointer; z-index: 2;">
                                <div style="border: 2px solid var(--accent); border-radius: 12px; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; color: var(--accent); background: #eef2ff;">
                                    <i class='bx bx-upload' style="font-size: 2.5rem;"></i>
                                    <span id="nombreArchivo" style="font-weight: bold; margin-top: 10px;">Seleccionar archivo</span>
                                </div>
                            </div>
                            <small style="display:block; margin-top:8px; color:#666; font-size:0.85rem;">
                                Formatos: PNG, JPG (Máx 2MB). Recomendado: fondo transparente.
                            </small>
                        </div>
                    </div>

                    <div style="text-align: right; border-top: 1px solid #eee; padding-top: 20px;">
                        <a href="/SIGED/public/index.php?action=home_docente" class="btn-outline" style="margin-right: 10px; text-decoration: none;">
                            Cancelar
                        </a>
                        <button type="submit" class="btn-modal" style="font-size: 1rem; padding: 12px 30px;">
                            Guardar Firma
                        </button>
                    </div>
                </form>
            </section>

        </div>
    </main>

    <script src="/SIGED/public/js/menu.js"></script>
    <script>
        // Script para cambiar el texto del input file
        const input = document.getElementById('archivoFirma');
        const label = document.getElementById('nombreArchivo');
        
        if (input) {
            input.addEventListener('change', function(e) {
                if (e.target.files[0]) {
                    label.textContent = e.target.files[0].name;
                    label.style.color = '#065f46'; // Verde oscuro
                } else {
                    label.textContent = 'Seleccionar archivo';
                }
            });
        }
    </script>
</body>
</html>