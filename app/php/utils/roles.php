<?php
declare(strict_types=1);
require_once __DIR__ . '/session.php';

function requireLogin(): void {
  if (!Session::user()) {
    header('Location: /siged/public/index.php?action=login');
    exit;
  }
}

function mapRol(int $idRol): string {
  return $idRol === 1 ? 'DOCENTE' :
         ($idRol === 2 ? 'JEFE_DEPARTAMENTO' :
         ($idRol === 3 ? 'SUBDIRECTOR_ACADEMICO' : 'DESCONOCIDO'));
}

function requireRole(array $roles): void {
  requireLogin();
  $user = Session::user();

  $rolActual = $user['rol'] ?? null;
  if ($rolActual === null && isset($user['id_rol'])) {
      $rolActual = mapRol((int)$user['id_rol']);
  }

  if (!in_array($rolActual, $roles, true)) {
    http_response_code(403);
    echo "<h1>403</h1><p>Acceso denegado para el rol actual.</p>";
    exit;
  }
}
