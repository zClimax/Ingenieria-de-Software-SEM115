<?php
declare(strict_types=1);

require_once __DIR__ . '/utils/session.php';
Session::start();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Login Subdirección | SIGED</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  
  <!-- VINCULACIÓN AL CSS SEPARADO -->
  <link rel="stylesheet" href="/SIGED/public/css/SubdLogin.css">
  
  <!-- Iconos y Fuentes -->
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
  
  <div class="page-wrapper">
    
    <div class="Contenedora-login-medio">
      <section class="Formularion-login">
        
        <!-- Logo -->
        <article class="logo-tecnm2">
            <img src="img/tecnm.png" alt="Logo TecNM">
        </article>

        <!-- Título -->
        <h1 class="TITULO-SIGED-GRANDE-AZUL">Subdirección Académica</h1>

        <!-- Formulario -->
        <form method="post" action="/SIGED/public/index.php?action=subd_auth" style="display: flex; flex-direction: column; gap: 15px;">
          
          <article class="labels-formulario">
            <label for="usuario">Usuario</label>
            <input type="text" id="usuario" name="usuario" required autocomplete="username" placeholder="Ingrese su usuario">
          </article>

          <article class="labels-formulario">
            <label for="contrasena">Contraseña</label>
            <input type="password" id="contrasena" name="contrasena" required autocomplete="current-password" placeholder="Ingrese su contraseña">
          </article>

          <article>
            <button type="submit" class="button-azul-completo">
              <i class='bx bx-log-in-circle'></i> Iniciar Sesión
            </button>
          </article>

        </form>

        <!-- Mensajes de Error -->
        <?php if (!empty($_GET['err'])): ?>
          <div class="mensaje-error">
            <i class='bx bx-error-circle'></i>
            <span>Credenciales inválidas o sin permisos.</span>
          </div>
        <?php endif; ?>

        <!-- Botón Volver -->
        <article>
            <button type="button" class="btn-volver" onclick="window.location.href='/SIGED/public/index.php'">
                <i class='bx bx-arrow-back'></i> Volver al Inicio
            </button>
        </article>

      </section>
    </div>

    <!-- Footer -->
    <footer class="footer-info">
        <p>&copy; <?php echo date('Y'); ?> SIGED - Sistema Integral de Gestión Escolar Docente</p>
        <p>Instituto Tecnológico de Culiacán</p>
    </footer>
  </div>

  <!-- JavaScript -->
  <script src="/SIGED/public/js/subd_login.js"></script>
</body>
</html>
