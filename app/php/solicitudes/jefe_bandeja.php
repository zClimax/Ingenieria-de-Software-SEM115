<?php
// filepath: c:\xampp\htdocs\SIGED\app\php\solicitudes\jefe_bandeja.php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';
requireRole(['JEFE_DEPARTAMENTO']);

$pdo = DB::conn();

$S = Config::MAP['SOLICITUD'];
$D = Config::MAP['DOCENTE'];
$U = Config::MAP['USUARIOS'];

$ts = $S['TABLE'];
$td = $D['TABLE'];
$tu = $U['TABLE'];

$user = Session::user();
$idUsuario = (int)$user['id'];

$depJefe = (int)($user['id_departamento'] ?? 0);
if ($depJefe <= 0) {
  $qDep = $pdo->prepare("SELECT ID_DEPARTAMENTO FROM dbo.USUARIOS WHERE ID_USUARIO=:u");
  $qDep->execute([':u' => (int)$user['id']]);
  $depJefe = (int)($qDep->fetchColumn() ?: 0);
}

// Correcciones pendientes
$sqlC = $pdo->prepare("
  SELECT c.ID_CORRECCION, c.ID_SOLICITUD, c.MOTIVO, c.CREATED_AT,
         s.TIPO_DOCUMENTO,
         d.NOMBRE_DOCENTE, d.APELLIDO_PATERNO_DOCENTE, d.APELLIDO_MATERNO_DOCENTE
  FROM dbo.DOC_CORRECCION c
  JOIN dbo.SOLICITUD_DOCUMENTO s ON s.ID_SOLICITUD = c.ID_SOLICITUD
  JOIN dbo.DOCENTE d ON d.ID_DOCENTE = s.ID_DOCENTE
  WHERE c.ESTATUS IN ('ABIERTA','EN_EDICION')
    AND c.ID_DEP_DESTINO = :dep
  ORDER BY c.CREATED_AT DESC
");
$sqlC->execute([':dep' => $depJefe]);
$correcciones = $sqlC->fetchAll(PDO::FETCH_ASSOC);

/* ✅ Obtener SIEMPRE el dep del jefe desde BD (evita caché de sesión) */
$sqlDepJefe = "SELECT {$U['DEP']} AS dep FROM $tu WHERE {$U['ID']} = :uid";
$stmt = $pdo->prepare($sqlDepJefe);
$stmt->execute([':uid' => $idUsuario]);
$rowDep = $stmt->fetch();
$idDepJefe = (int)($rowDep['dep'] ?? 0);

/* Modo diagnóstico: ver todas las ENVIADAS sin filtrar por depto */
$showAll = isset($_GET['all']) && $_GET['all']=='1';

if ($showAll) {
  $sql = "
    SELECT 
      S.{$S['ID']}       AS id,
      S.{$S['TIPO']}     AS tipo,
      S.{$S['F_ENV']}    AS enviada,
      S.{$S['DEP_APROB']} AS dep_aprob,
      D.{$D['NOMBRE']}   AS nombre,
      D.{$D['AP_PAT']}   AS ap,
      D.{$D['AP_MAT']}   AS am
    FROM $ts S
    JOIN $td D ON D.{$D['ID']} = S.{$S['DOC']}
    WHERE RTRIM(LTRIM(S.{$S['ESTADO']})) = 'ENVIADA'
    ORDER BY S.{$S['ID']} DESC";
  $stmt = $pdo->query($sql);
} else {
  $sql = "
    SELECT 
      S.{$S['ID']}       AS id,
      S.{$S['TIPO']}     AS tipo,
      S.{$S['F_ENV']}    AS enviada,
      D.{$D['NOMBRE']}   AS nombre,
      D.{$D['AP_PAT']}   AS ap,
      D.{$D['AP_MAT']}   AS am
    FROM $ts S
    JOIN $td D ON D.{$D['ID']} = S.{$S['DOC']}
    WHERE RTRIM(LTRIM(S.{$S['ESTADO']})) = 'ENVIADA'
      AND S.{$S['DEP_APROB']} = :depAprob
    ORDER BY S.{$S['ID']} DESC";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':depAprob' => $idDepJefe]);
}
$rows = $stmt->fetchAll();

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
    <title>Bandeja de Validación | SIGED</title>
    
    <link rel="stylesheet" href="/SIGED/public/css/JefeBandeja.css">
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
                    <a href="/SIGED/public/index.php?action=jefe_bandeja" class="nav-link active">
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
        <div class="bandeja-wrapper">

            <!-- Header de la bandeja -->
            <section class="bandeja-header">
                <div class="header-top">
                    <div class="title-section">
                        <h1 class="page-title">
                            <i class='bx bx-folder-open'></i>
                            Bandeja de validación
                        </h1>
                        <p class="page-subtitle">Gestiona las solicitudes pendientes de tu departamento</p>
                    </div>
                    <div class="header-actions">
                        <div class="info-badge">
                            <i class='bx bx-building'></i>
                            <span>Departamento: <strong><?= (int)$idDepJefe ?></strong></span>
                        </div>
                        <?php if ($showAll): ?>
                            <a href="/SIGED/public/index.php?action=jefe_bandeja" class="btn-secondary">
                                <i class='bx bx-filter'></i>
                                Filtrar por mi departamento
                            </a>
                        <?php else: ?>
                            <a href="/SIGED/public/index.php?action=jefe_bandeja&all=1" class="btn-secondary">
                                <i class='bx bx-show'></i>
                                Ver todas (diagnóstico)
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Estadísticas rápidas -->
                <div class="stats-row">
                    <div class="stat-card">
                        <div class="stat-icon" style="color:#f59e0b;">
                            <i class='bx bx-time-five'></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-label">Solicitudes pendientes</span>
                            <span class="stat-value"><?= count($rows) ?></span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="color:#ef4444;">
                            <i class='bx bx-error'></i>
                        </div>
                        <div class="stat-content">
                            <span class="stat-label">Correcciones pendientes</span>
                            <span class="stat-value"><?= count($correcciones) ?></span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Correcciones pendientes -->
            <?php if (count($correcciones) > 0): ?>
            <section class="correcciones-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class='bx bx-error-circle'></i>
                        Correcciones pendientes
                        <span class="badge-count"><?= count($correcciones) ?></span>
                    </h2>
                </div>
                <div class="correcciones-grid">
                    <?php foreach ($correcciones as $c): ?>
                    <div class="correccion-card">
                        <div class="correccion-header">
                            <span class="solicitud-id">#<?= (int)$c['ID_SOLICITUD'] ?></span>
                            <span class="badge-tipo"><?= htmlspecialchars($c['TIPO_DOCUMENTO']) ?></span>
                        </div>
                        <div class="correccion-body">
                            <div class="docente-info">
                                <i class='bx bx-user'></i>
                                <span><?= htmlspecialchars(trim($c['NOMBRE_DOCENTE'].' '.$c['APELLIDO_PATERNO_DOCENTE'].' '.$c['APELLIDO_MATERNO_DOCENTE'])) ?></span>
                            </div>
                            <div class="motivo">
                                <i class='bx bx-message-square-detail'></i>
                                <p>"<?= htmlspecialchars($c['MOTIVO']) ?>"</p>
                            </div>
                            <div class="fecha-info">
                                <i class='bx bx-calendar'></i>
                                <span><?= date('d/m/Y H:i', strtotime($c['CREATED_AT'])) ?></span>
                            </div>
                        </div>
                        <div class="correccion-footer">
                            <a href="/SIGED/public/index.php?action=corr_editar&id=<?= (int)$c['ID_SOLICITUD'] ?>" class="btn-atender">
                                <i class='bx bx-edit'></i>
                                Atender corrección
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <!-- Solicitudes pendientes -->
            <section class="solicitudes-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class='bx bx-file'></i>
                        Solicitudes enviadas
                        <span class="badge-count"><?= count($rows) ?></span>
                    </h2>
                </div>

                <?php if (count($rows) === 0): ?>
                    <div class="empty-state">
                        <i class='bx bx-check-circle'></i>
                        <h3>Sin solicitudes pendientes</h3>
                        <p>No hay solicitudes ENVIADAS para validar en este momento</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="solicitudes-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tipo de documento</th>
                                    <th>Docente</th>
                                    <th>Fecha de envío</th>
                                    <?php if ($showAll): ?><th>Dpto. Aprob.</th><?php endif; ?>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($rows as $r): ?>
                                <tr>
                                    <td class="solicitud-id-cell">
                                        <span class="id-badge">#<?= (int)$r['id']?></span>
                                    </td>
                                    <td>
                                        <span class="tipo-documento"><?= htmlspecialchars($r['tipo'])?></span>
                                    </td>
                                    <td>
                                        <div class="docente-cell">
                                            <i class='bx bx-user-circle'></i>
                                            <span><?= htmlspecialchars(trim(($r['nombre']??'').' '.($r['ap']??'').' '.($r['am']??''))) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fecha-cell">
                                            <i class='bx bx-calendar'></i>
                                            <span><?= $r['enviada'] ? date('d/m/Y', strtotime($r['enviada'])) : '—' ?></span>
                                        </div>
                                    </td>
                                    <?php if ($showAll && isset($r['dep_aprob'])): ?>
                                        <td class="text-center"><?= (int)$r['dep_aprob']?></td>
                                    <?php endif; ?>
                                    <td>
                                        <a href="/SIGED/public/index.php?action=jefe_ver&id=<?= (int)$r['id']?>" class="btn-revisar">
                                            <i class='bx bx-search-alt'></i>
                                            Revisar
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

        </div>
    </main>

    <!-- Scripts -->
    <script src="/SIGED/public/js/menu.js"></script>
    <script src="/SIGED/public/js/jefe_bandeja.js"></script>
</body>
</html>
