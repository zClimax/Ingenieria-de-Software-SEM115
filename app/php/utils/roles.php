<?php
declare(strict_types=1);
require_once __DIR__ . '/session.php';

function requireLogin(): void {
  if (!Session::user()) {
    header('Location: /siged/public/index.php?action=login');
    exit;
  }
}

function requireRole(array $roles): void {
  requireLogin();
  $user = Session::user();
  if (!in_array($user['rol'], $roles, true)) {
    http_response_code(403);
    echo "<h1>403</h1><p>Acceso denegado para el rol actual.</p>";
    exit;
  }
}

/** Mapea ID_ROL numérico a etiqueta de rol */
function mapRol(int $idRol): string {
  return $idRol === 1 ? 'DOCENTE' : ($idRol === 2 ? 'JEFE_DEPARTAMENTO' : 'DESCONOCIDO');
}
