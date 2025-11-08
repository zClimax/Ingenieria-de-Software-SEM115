<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php';
$user = Session::user();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>SIGED · Jefe de Departamento</title>
  <link rel="stylesheet" href="/siged/app/css/styles.css">
  <script defer src="/siged/app/js/main.js"></script>
</head>
<body class="layout">
  <header class="topbar">
    <strong>SIGED</strong>
    <nav>
      <a href="/siged/public/index.php?action=pdf_demo">PDF demo</a>
      <a href="/siged/public/index.php?action=logout">Salir</a>
    </nav>
  </header>
  <main class="card">
    <h1>Bienvenido(a), <?= htmlspecialchars($user['nombre'] ?? 'Jefe de Departamento') ?></h1>
    <ul>
      <li>Validación de expedientes (próximo)</li>
      <li>Revisión de documentos (próximo)</li>
      <li>Convocatorias y requisitos (próximo)</li>
      <li>Tickets (próximo)</li>
    </ul>
    <p>
  <a href="/siged/public/index.php?action=jefe_bandeja">Bandeja de validación</a>
    </p>
  </main>
</body>
</html>
