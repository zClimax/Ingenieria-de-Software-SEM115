<?php
// filepath: c:\xampp\htdocs\SIGEEED\app\php\usuarios\jefe_foto_upload.php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';

Session::start();
$user = Session::user();
$uId = (int)($user['id'] ?? 0);

if ($uId <= 0) {
    header('Location: /SIGEEED/public/index.php?action=home_jefe&msg=foto_error');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /SIGEEED/public/index.php?action=home_jefe');
    exit;
}

// Validar archivo
if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
    header('Location: /SIGEEED/public/index.php?action=home_jefe&msg=foto_error');
    exit;
}

$file = $_FILES['foto'];

// Validar tipo
$allowedTypes = ['image/jpeg', 'image/jpg'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes)) {
    header('Location: /SIGEEED/public/index.php?action=home_jefe&msg=foto_tipo');
    exit;
}

// Validar tamaño (2 MB)
if ($file['size'] > 2 * 1024 * 1024) {
    header('Location: /SIGEEED/public/index.php?action=home_jefe&msg=foto_pesada');
    exit;
}

// Crear carpeta si no existe
$carpetaFotos = __DIR__ . '/../../../storage/fotos';
if (!is_dir($carpetaFotos)) {
    mkdir($carpetaFotos, 0755, true);
}

// Guardar archivo
$nombreArchivo = 'jefe_' . $uId . '.jpg';
$rutaDestino = $carpetaFotos . '/' . $nombreArchivo;

if (!move_uploaded_file($file['tmp_name'], $rutaDestino)) {
    header('Location: /SIGEEED/public/index.php?action=home_jefe&msg=foto_error');
    exit;
}

// Éxito
header('Location: /SIGEEED/public/index.php?action=home_jefe&msg=foto_ok');
exit;