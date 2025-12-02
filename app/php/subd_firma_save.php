<?php
declare(strict_types=1);

require_once __DIR__ . '/utils/session.php';
require_once __DIR__ . '/config.php';

Session::start();
$pdo  = DB::conn();
$user = Session::user() ?: [];
$uid  = (int)($user['id'] ?? 0);

if ($uid <= 0) {
    http_response_code(403);
    exit('Sesión inválida');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido');
}

if (!isset($_FILES['firma']) || $_FILES['firma']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    exit('Archivo de firma requerido');
}

$f = $_FILES['firma'];

// Validar extensión
$ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
$allowed = ['png', 'jpg', 'jpeg'];
if (!in_array($ext, $allowed, true)) {
    http_response_code(400);
    exit('Tipo de archivo no permitido. Usa PNG o JPG.');
}

// ========================
// 1) Ruta física correcta
//    C:\xampp\htdocs\SIGED\storage\firmas
// ========================
$PROJ_ROOT = str_replace('\\', '/', dirname(__DIR__, 2)); // sube 2 niveles desde app/php
$dirAbs    = $PROJ_ROOT . '/storage/firmas';

if (!is_dir($dirAbs)) {
    if (!mkdir($dirAbs, 0775, true) && !is_dir($dirAbs)) {
        http_response_code(500);
        exit('No se pudo crear el directorio de firmas');
    }
}

// Nombre de archivo
$filename = sprintf(
    'firma_%d_%s.%s',
    $uid,
    date('Ymd_His'),
    $ext
);

$rutaAbs  = $dirAbs . '/' . $filename;

// ========================
// 2) Mover archivo subido
// ========================
if (!move_uploaded_file($f['tmp_name'], $rutaAbs)) {
    http_response_code(500);
    exit('No se pudo guardar la firma en disco');
}

// ========================
// 3) Calcular hash HEX
// ========================
$bin = file_get_contents($rutaAbs);
if ($bin === false) {
    http_response_code(500);
    exit('No se pudo leer la firma para calcular el hash');
}

// Hex de 64 caracteres (SHA-256 => 32 bytes => 64 chars)
$hashHex = hash('sha256', $bin); // p.ej. "0a4f...."

// ========================
// 4) Actualizar en BD
//     - RUTA_FIRMA  (string tipo "storage/firmas/firma_10_...png")
//     - FIRMA_MIME  (image/png)
//     - FIRMA_HASH  (varbinary usando CONVERT)
//     - FECHA_FIRMA (GETDATE())
// ========================

$rutaFirmaDb = 'storage/firmas/' . $filename;

$sql = "
  UPDATE dbo.USUARIOS
  SET RUTA_FIRMA  = :ruta,
      FIRMA_MIME  = :mime,
      FIRMA_HASH  = CONVERT(varbinary(32), :hash, 2),
      FECHA_FIRMA = GETDATE()
  WHERE ID_USUARIO = :id
";

$st = $pdo->prepare($sql);
$st->execute([
    ':ruta' => $rutaFirmaDb,
    ':mime' => (string)($f['type'] ?? 'image/'.$ext),
    ':hash' => $hashHex,   // <- HEX, no binario
    ':id'   => $uid,
]);


// ========================
// 5) Volver a la vista de subdirección
// ========================
header('Location: /SIGED/public/index.php?action=subd_docs');
exit;
