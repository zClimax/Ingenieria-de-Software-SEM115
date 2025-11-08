<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/php/utils/Session.php';
Session::start();

require_once __DIR__ . '/../app/php/routes.php';

// Si no hay acción, decidimos: login o home por rol
$action = $_GET['action'] ?? '';
if ($action) {
  route($action);
  exit;
}

if (!Session::user()) {
  header('Location: ?action=login');
  exit;
}

// Redirige según rol
$user = Session::user();
if ($user['rol'] === 'DOCENTE') {
  header('Location: ?action=home_docente');
} elseif ($user['rol'] === 'JEFE_DEPARTAMENTO') {
  header('Location: ?action=home_jefe');
} else {
  // Rol desconocido -> forzamos logout por seguridad
  header('Location: ?action=logout');
}
