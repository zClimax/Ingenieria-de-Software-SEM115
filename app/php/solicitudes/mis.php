<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';

Session::start();
$pdo  = DB::conn();
$user = (array)(Session::user() ?? []);
$uid  = (int)($user['id'] ?? 0);
if ($uid <= 0) { http_response_code(403); exit('Sesión inválida'); }

// ===== Helpers
function corrOpen(PDO $pdo, int $idSol): bool {
  $q = $pdo->prepare("SELECT 1
                      FROM dbo.DOC_CORRECCION
                      WHERE ID_SOLICITUD=:id AND ESTATUS IN('ABIERTA','EN_EDICION')");
  $q->execute([':id'=>$idSol]);
  return (bool)$q->fetchColumn();
}

$sql = "
SELECT
  S.ID_SOLICITUD         AS id,
  S.TIPO_DOCUMENTO       AS tipo,
  S.ESTADO               AS estado,
  S.FECHA_CREACION       AS f_crea,
  S.FECHA_ENVIO          AS f_env,
  S.FECHA_DECISION       AS f_dec,
  S.FOLIO                AS folio,
  S.COMENTARIO_JEFE      AS comentario
FROM [SIGED].[dbo].[SOLICITUD_DOCUMENTO] S
JOIN [SIGED].[dbo].[DOCENTE] D   ON D.ID_DOCENTE  = S.ID_DOCENTE
JOIN [SIGED].[dbo].[USUARIOS] U  ON U.ID_USUARIO  = D.ID_USUARIO
WHERE U.ID_USUARIO = :uid
ORDER BY S.ID_SOLICITUD DESC";
$st = $pdo->prepare($sql);
$st->execute([':uid' => $uid]);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);

function badgeEstado(string $e): array {
  return match (strtoupper($e)) {
    'APROBADA'  => ['APROBADA',  'badge-aprobada'],
    'ENVIADA'   => ['ENVIADA',   'badge-enviada'],
    'RECHAZADA' => ['RECHAZADA', 'badge-rechazada'],
    default     => ['BORRADOR',  'badge-borrador'],
  };
}
function chipTipo(string $t): array {
  return [strtoupper($t), 'badge-tipo'];
}

// Datos del usuario para el footer
$nombreCompleto = $user['nombre'] ?? 'Usuario';

// Ruta de foto de perfil
$rutaFoto = 'storage/fotos/doc_' . $uid . '.jpg';
$rutaFisica = __DIR__ . '/../../../' . $rutaFoto;
if (file_exists($rutaFisica)) {
    $imgSrc = '../' . $rutaFoto . '?v=' . time(); 
} else {
    $imgSrc = '';
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>SIGED · Mis solicitudes</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/SIGED/public/css/MisSolicitudes.css">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    /* Deshabilitados visualmente y sin interacción */
    .btn-disabled{
      opacity:.45;
      cursor:not-allowed !important;
      pointer-events:none !important;
    }
     /* Comentario oculto por defecto */
  .fila-comentario {
    display: none;
  }

  /* Comentario visible cuando se marca */
  .fila-comentario.visible {
    display: table-row;
  }
  </style>
</head>
<body>
  <!-- HEADER -->
  <header class="header">
    <div class="header-left">
      <div class="menu-icon" id="menuToggle">
        <span></span>
        <span></span>
        <span></span>
      </div>
    </div>
    <div class="header-center">
      <a href="/SIGED/public/index.php?action=home_docente" title="SIGED - Inicio">
        <img src="/SIGED/public/img/IconosSIged/Recurso 3SIGED_LOGO.png" alt="SIGED Logo" class="header-logo">
      </a>
    </div>
    <div class="header-right">
      <button class="btn-salir" onclick="location.href='/SIGED/public/index.php?action=logout'">Salir</button>
    </div>
  </header>

  <!-- SIDEBAR -->
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
          <a href="/SIGED/public/index.php?action=sol_mis" class="nav-link active">
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
  </aside>

  <!-- CONTENIDO PRINCIPAL -->
  <main class="main-content">
    <div class="content-wrapper">
      
      <!-- TARJETA DE FILTROS -->
      <section class="filtros-card">
        <h2 class="titulo-seccion"><i class='bx bx-filter'></i> Filtros de búsqueda</h2>
        <div class="filtros-grid">
          <div class="filtro-item">
            <label>Estado</label>
            <select class="filtro-select" id="filtroEstado">
              <option value="">Todos</option>
              <option value="BORRADOR">Borrador</option>
              <option value="ENVIADA">Enviada</option>
              <option value="APROBADA">Aprobada</option>
              <option value="RECHAZADA">Rechazada</option>
            </select>
          </div>
          <div class="filtro-item">
            <label>Buscar por folio o tipo</label>
            <input type="search" class="filtro-input" id="busqueda" placeholder="Escribe para buscar...">
          </div>
        </div>
      </section>

      <!-- TARJETA DE SOLICITUDES -->
      <section class="solicitudes-card">
        <div class="card-header-flex">
          <h2 class="titulo-seccion"><i class='bx bx-list-ul'></i> Mis Solicitudes</h2>
          <a href="/SIGED/public/index.php?action=sol_nueva" class="btn-nuevo">
            <i class='bx bx-plus'></i>
            Nueva Solicitud
          </a>
        </div>

        <?php if (!$rows): ?>
          <div class="tabla-vacia">
            <i class='bx bx-inbox'></i>
            <p>Aún no tienes solicitudes. <a href="/SIGED/public/index.php?action=sol_nueva" class="link-crear">Crear la primera</a>.</p>
          </div>
        <?php else: ?>
          <div class="tabla-container">
            <table class="tabla-solicitudes" id="tablaSolicitudes">
              <thead>
                <tr>
                  <th>ID / Folio</th>
                  <th>Tipo</th>
                  <th>Estado</th>
                  <th>Fechas</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($rows as $r):
                $id     = (int)$r['id'];
                $folio  = trim((string)($r['folio'] ?? ''));
                [$tText,$tClass] = chipTipo((string)$r['tipo']);
                [$eText,$eClass] = badgeEstado((string)$r['estado']);
                $estadoUp = strtoupper($eText);
                $fC = $r['f_crea'] ? substr((string)$r['f_crea'],0,19) : '—';
                $fE = $r['f_env']  ? substr((string)$r['f_env'],0,19)  : '—';
                $fD = $r['f_dec']  ? substr((string)$r['f_dec'],0,19)  : '—';
                $coment = trim((string)($r['comentario'] ?? ''));
              ?>
                <tr data-status="<?= htmlspecialchars($estadoUp) ?>" data-id="<?= $id ?>">
                  <td>
                    <div class="celda-id">#<?= $id ?></div>
                    <?php if ($folio): ?>
                      <div class="celda-folio">Folio: <?= htmlspecialchars($folio) ?></div>
                    <?php endif; ?>
                    <div class="celda-fecha">
                      <i class='bx bx-calendar'></i> <?= htmlspecialchars($fC) ?>
                    </div>
                  </td>
                  <td>
                    <span class="badge <?= $tClass ?>"><?= htmlspecialchars($tText) ?></span>
                  </td>
                  <td>
                    <span class="badge <?= $eClass ?>"><?= htmlspecialchars($eText) ?></span>
                    <?php if ($estadoUp==='ENVIADA'): ?>
                      <div class="info-extra">Enviada: <?= htmlspecialchars($fE) ?></div>
                    <?php elseif (in_array($estadoUp, ['APROBADA','RECHAZADA'])): ?>
                      <div class="info-extra">Decidida: <?= htmlspecialchars($fD) ?></div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="fecha-info">
                      <i class='bx bx-paper-plane'></i> Envío: <?= htmlspecialchars($fE) ?>
                    </div>
                    <div class="fecha-info">
                      <i class='bx bx-check-circle'></i> Decisión: <?= htmlspecialchars($fD) ?>
                    </div>
                  </td>
                  <td>
                    <div class="acciones-group">
                      <?php if ($estadoUp==='BORRADOR'): ?>
                        <a href="/SIGED/public/index.php?action=sol_editar&id=<?= $id ?>" class="btn-icon btn-ver" title="Editar">
                          <i class='bx bx-edit'></i>
                        </a>
                        <a href="/SIGED/public/index.php?action=sol_enviar&id=<?= $id ?>" class="btn-icon btn-enviar" title="Enviar" onclick="return confirm('¿Enviar la solicitud #<?= $id ?>?');">
                          <i class='bx bx-paper-plane'></i>
                        </a>
                        <?php if ($coment): ?>
                          <button class="btn-icon btn-comentario" title="Ver comentario" onclick="toggleComentario(<?= $id ?>)">
                            <i class='bx bx-comment'></i>
                          </button>
                        <?php endif; ?>

                      <?php elseif ($estadoUp==='RECHAZADA'): ?>
                        <span class="btn-icon btn-disabled" title="No disponible en solicitudes rechazadas">
                          <i class='bx bx-edit'></i>
                        </span>
                        <span class="btn-icon btn-disabled" title="No disponible en solicitudes rechazadas">
                          <i class='bx bx-paper-plane'></i>
                        </span>
                        <?php if ($coment): ?>
                          <button class="btn-icon btn-comentario" title="Ver comentario" onclick="toggleComentario(<?= $id ?>)">
                            <i class='bx bx-comment'></i>
                          </button>
                        <?php endif; ?>

                      <?php elseif ($estadoUp==='ENVIADA'): ?>
                        <a href="/SIGED/public/index.php?action=sol_editar&id=<?= $id ?>" class="btn-icon btn-ver" title="Editar">
                          <i class='bx bx-edit'></i>
                        </a>

                      <?php elseif ($estadoUp==='APROBADA'): ?>
                        <a href="/SIGED/public/index.php?action=doc_pdf&id=<?= $id ?>" class="btn-icon btn-pdf" title="Generar PDF">
                          <i class='bx bxs-file-pdf'></i>
                        </a>
                        <?php if (!corrOpen($pdo, $id)): ?>
                          <a href="/SIGED/public/index.php?action=sol_corr_new&id=<?= $id ?>" class="btn-icon btn-correccion" title="Solicitar corrección">
                            <i class='bx bx-error'></i>
                          </a>
                        <?php else: ?>
                          <span class="badge insignia-pendiente">
                            <i class='bx bx-time'></i> Corrección en proceso
                          </span>
                        <?php endif; ?>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
                <?php if ($coment): ?>
                <tr id="comentario-<?= $id ?>" class="fila-comentario">
                  <td colspan="5">
                    <div class="comentario-box">
                      <div class="comentario-content">
                        <i class='bx bx-comment-dots'></i>
                        <div>
                          <strong class="comentario-titulo">Comentario del Jefe:</strong>
                          <p class="comentario-texto"><?= nl2br(htmlspecialchars($coment)) ?></p>
                        </div>
                      </div>
                    </div>
                  </td>
                </tr>
                <?php endif; ?>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
    </div>
  </main>

  <script>
    // Toggle sidebar
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    menuToggle.addEventListener('click', () => {
      sidebar.classList.toggle('minimized');
    });

    // Filtro por estado
    const filtroEstado = document.getElementById('filtroEstado');
    const busqueda = document.getElementById('busqueda');
    const filas = document.querySelectorAll('#tablaSolicitudes tbody tr[data-status]');

    function aplicarFiltros() {
  const estadoSeleccionado = filtroEstado.value.toUpperCase();
  const textoBusqueda = busqueda.value.toLowerCase();

  filas.forEach(fila => {
    const estado = fila.getAttribute('data-status');
    const texto = fila.innerText.toLowerCase();
    const id = fila.getAttribute('data-id');
    
    const cumpleEstado = !estadoSeleccionado || estado === estadoSeleccionado;
    const cumpleBusqueda = !textoBusqueda || texto.includes(textoBusqueda);
    
    const visible = cumpleEstado && cumpleBusqueda;
    fila.style.display = visible ? '' : 'none';
    
    // Si la fila principal se oculta, también ocultamos el comentario,
    // pero solo quitando la clase, SIN tocar style.display.
    const comentario = document.getElementById('comentario-' + id);
    if (comentario && !visible) {
      comentario.classList.remove('visible');
    }
  });
}


    filtroEstado.addEventListener('change', aplicarFiltros);
    busqueda.addEventListener('input', aplicarFiltros);

// Toggle comentario
window.toggleComentario = function(id) {
  const row = document.getElementById('comentario-' + id);
  if (!row) {
    console.warn('No se encontró la fila de comentario para la solicitud', id);
    return;
  }
  row.classList.toggle('visible');
};

  </script>
</body>
</html>
