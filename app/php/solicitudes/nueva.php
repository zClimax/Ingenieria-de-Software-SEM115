<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
$user = Session::user();
?>
<!doctype html>
<html lang="es"><head>
<meta charset="utf-8"><title>Nueva solicitud</title>
<link rel="stylesheet" href="/siged/app/css/styles.css">
</head><body class="layout">
  <main class="card">
    <h1>Nueva solicitud de documento</h1>
    <form method="post" action="/siged/public/index.php?action=sol_guardar">
      <label>Tipo de documento
        <select name="tipo" required>
          <option value="">-- Selecciona --</option>
          <option value="DCE">DCE</option>
          <option value="DCL">DCL</option>
          <option value="RCP">RCP</option>
          <option value="SIP">SIP</option>
        </select>
      </label>
      <button type="submit">Crear BORRADOR</button>
      <a href="/siged/public/index.php?action=home_docente">Cancelar</a>
    </form>
  </main>
</body></html>
