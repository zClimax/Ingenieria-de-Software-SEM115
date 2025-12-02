<?php
declare(strict_types=1);

require_once __DIR__ . '/utils/session.php';
Session::start();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Login Subdirección Académica | SIGED</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/SIGED/public/css/Login.css"><!-- si ya tienes uno, reutilízalo -->
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
</head>
<body>
  <div class="login-container">
    <div class="login-card">
      <h1>Acceso Subdirección Académica</h1>
      <form method="post" action="/SIGED/public/index.php?action=subd_auth">
        <div class="form-group">
          <label>Usuario</label>
          <input type="text" name="usuario" required autocomplete="username">
        </div>
        <div class="form-group">
          <label>Contraseña</label>
          <input type="password" name="contrasena" required autocomplete="current-password">
        </div>
        <button type="submit" class="btn-login">
          <i class='bx bx-log-in-circle'></i> Entrar
        </button>
      </form>

      <?php if (!empty($_GET['err'])): ?>
        <div class="alert error" style="margin-top:.75rem">
          Credenciales inválidas o usuario sin perfil de Subdirector Académico.
        </div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
