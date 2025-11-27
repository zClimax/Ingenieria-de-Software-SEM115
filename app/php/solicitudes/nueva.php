<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';

Session::start();
$pdo  = DB::conn();
$user = (array)(Session::user() ?? []);
$uid  = (int)($user['id'] ?? 0);
if ($uid <= 0) { http_response_code(403); exit('Sesión inválida'); }

// 1) Cargar convocatorias activas
$st = $pdo->query("
  SELECT ID_CONVOCATORIA, NOMBRE_CONVOCATORIA, ANIO
  FROM dbo.CONVOCATORIA
  WHERE ACTIVO = 1
  ORDER BY ANIO DESC, ID_CONVOCATORIA DESC
");
$convs = $st->fetchAll(PDO::FETCH_ASSOC);

// 2) Cargar tipos de documento disponibles
$st2 = $pdo->query("
  SELECT DISTINCT M.TIPO_DOCUMENTO, ET.NOMBRE, ET.PUNTAJE
  FROM dbo.EDD_EVIDENCIA_MAP M
  INNER JOIN dbo.EDD_EVIDENCIA_TIPO ET
    ON ET.CODIGO = M.CODIGO_EVIDENCIA AND ET.ACTIVO = 1
  ORDER BY ET.NOMBRE
");
$tipos = $st2->fetchAll(PDO::FETCH_ASSOC);

// Parámetros preseleccionados
$tipoPre = strtoupper(trim((string)($_GET['tipo'] ?? '')));
$convPre = (int)($_GET['conv'] ?? 0);

// Datos del usuario
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
  <title>SIGED · Nueva solicitud</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/SIGED/public/css/NuevaSolicitud.css">
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
      <!-- <a href="/SIGED/public/index.php?action=notificaciones" class="enlace-notificacion">
        <i class='bx bx-bell'></i> -->
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
          <a href="/SIGED/public/index.php?action=sol_mis" class="nav-link">
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
  </aside>

  <!-- CONTENIDO PRINCIPAL -->
  <main class="contenido-principal">
    <div class="contenedor-contenido">
      
      <!-- ALERTA SI NO HAY CONVOCATORIAS -->
      <?php if (!$convs): ?>
        <div class="alerta-advertencia">
          <i class='bx bx-error-circle'></i>
          <div>
            <strong>No hay convocatorias activas</strong>
            <p>Solicita a tu área que active una convocatoria para poder crear solicitudes.</p>
          </div>
        </div>
      <?php endif; ?>

      <!-- TARJETA FORMULARIO -->
      <section class="tarjeta-formulario">
        <h2 class="titulo-seccion">Nueva solicitud</h2>

        <form method="post" action="/SIGED/public/index.php?action=sol_guardar" class="formulario-solicitud">
          <div class="cuadricula-campos">
            <!-- TIPO DE DOCUMENTO -->
            <div class="grupo-campo">
              <label for="tipo">Tipo de documento</label>
              <select id="tipo" name="tipo" class="campo-seleccion" required>
                <option value="" disabled <?= $tipoPre ? '' : 'selected' ?>>Selecciona un tipo...</option>
                <?php foreach ($tipos as $t):
                  $val = strtoupper((string)$t['TIPO_DOCUMENTO']);
                  $nom = (string)$t['NOMBRE'];
                  $pts = (int)$t['PUNTAJE'];
                ?>
                  <option value="<?= htmlspecialchars($val) ?>" <?= $tipoPre === $val ? 'selected' : '' ?>>
                    <?= htmlspecialchars($nom) ?> (<?= htmlspecialchars($val) ?><?= $pts>0 ? ' · '.$pts.' pts' : '' ?>)
                  </option>
                <?php endforeach; ?>
              </select>
              <small class="texto-ayuda">Los tipos listados están vinculados a evidencias activas</small>
            </div>

            <!-- CONVOCATORIA -->
            <div class="grupo-campo">
              <label for="conv">Convocatoria</label>
              <?php if (count($convs) <= 1):
                $convId = $convs ? (int)$convs[0]['ID_CONVOCATORIA'] : 0; ?>
                <input type="hidden" id="conv" name="convocatoria_id" value="<?= $convPre ?: $convId ?>">
                <div class="campo-readonly">
                  <?= $convs ? htmlspecialchars($convs[0]['NOMBRE_CONVOCATORIA'].' ('.$convs[0]['ANIO'].')') : '—' ?>
                </div>
              <?php else: ?>
                <select id="conv" name="convocatoria_id" class="campo-seleccion" required>
                  <?php foreach ($convs as $c): ?>
                    <option value="<?= (int)$c['ID_CONVOCATORIA'] ?>" <?= $convPre===(int)$c['ID_CONVOCATORIA'] ? 'selected':'' ?>>
                      <?= htmlspecialchars($c['NOMBRE_CONVOCATORIA'].' ('.$c['ANIO'].')') ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              <?php endif; ?>
              <small class="texto-ayuda">Se usará para folios, validación y puntaje</small>
            </div>
          </div>

          <!-- BOTONES -->
          <div class="grupo-botones">
            <button type="submit" class="boton-primario" <?= $convs ? '' : 'disabled' ?>>
              <i class='bx bx-save'></i>
              Guardar solicitud
            </button>
            <a class="boton-secundario" href="/SIGED/public/index.php?action=sol_mis">
              <i class='bx bx-x'></i>
              Cancelar
            </a>
          </div>
        </form>
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
  </script>
</body>
</html>
