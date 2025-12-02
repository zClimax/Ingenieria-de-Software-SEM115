<?php
declare(strict_types=1);

require_once __DIR__ . '/utils/session.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/utils/roles.php'; // 👈 para usar mapRol()

Session::start();

$pdo = DB::conn();

$usuario    = trim((string)($_POST['usuario'] ?? ''));
$contrasena = trim((string)($_POST['contrasena'] ?? ''));

if ($usuario === '' || $contrasena === '') {
    header('Location: /SIGED/public/index.php?action=subd_login&err=1');
    exit;
}

$sql = "
    SELECT 
      ID_USUARIO,
      ID_ROL,
      ID_DEPARTAMENTO,
      NOMBRE_USUARIO,
      NOMBRE_COMPLETO,
      CORREO,
      ACTIVO
    FROM dbo.USUARIOS
    WHERE NOMBRE_USUARIO = :u
      AND CONTRASENA     = :p
      AND ACTIVO         = 1
      AND ID_ROL         = 3   -- Subdirección académica
";

$st = $pdo->prepare($sql);
$st->execute([
  ':u' => $usuario,
  ':p' => $contrasena,
]);
$row = $st->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    header('Location: /SIGED/public/index.php?action=subd_login&err=1');
    exit;
}

// 👇 mapeamos ID_ROL -> etiqueta de rol como usa todo el sistema
$rolLabel = mapRol((int)$row['ID_ROL']);

$userData = [
    'id'              => (int)$row['ID_USUARIO'],
    'id_rol'          => (int)$row['ID_ROL'],
    'rol'             => $rolLabel, // 👈 CLAVE CRÍTICA
    'id_departamento' => (int)($row['ID_DEPARTAMENTO'] ?? 0),
    'usuario'         => (string)$row['NOMBRE_USUARIO'],
    'nombre'          => (string)($row['NOMBRE_COMPLETO'] ?? $row['NOMBRE_USUARIO']),
    'correo'          => (string)($row['CORREO'] ?? ''),
];

if (method_exists('Session', 'setUser')) {
    Session::setUser($userData);
} else {
    $_SESSION['user'] = $userData;
}

header('Location: /SIGED/public/index.php?action=subd_docs');
exit;
