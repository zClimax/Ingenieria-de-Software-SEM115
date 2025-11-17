<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';

header('Content-Type: application/json; charset=UTF-8');

try {
  requireRole(['DOCENTE']);
  Session::start();

  $u = (array)(Session::user() ?? []);
  $idUsuario = (int)($u['id'] ?? 0);
  if ($idUsuario <= 0) { http_response_code(401); echo json_encode(['ok'=>false,'msg'=>'No autenticado']); exit; }

  if (empty($_FILES['foto']) || !is_uploaded_file($_FILES['foto']['tmp_name'])) {
    http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'Archivo no recibido']); exit;
  }

  $file = $_FILES['foto'];
  if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'Error de carga ('.$file['error'].')']); exit;
  }

  // Máx 2MB
  if ($file['size'] > 2 * 1024 * 1024) {
    http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'El archivo supera 2 MB']); exit;
  }

  // Validar mimetype básico
  $mime = mime_content_type($file['tmp_name']);
  $ext  = '';
  if ($mime === 'image/jpeg' || $mime === 'image/pjpeg') $ext = 'jpg';
  elseif ($mime === 'image/png') $ext = 'png';
  else { http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'Formato no permitido (solo JPG/PNG)']); exit; }

  // Paths
  $root   = str_replace('\\','/', realpath(__DIR__ . '/../../..'));      // …/SIGED
  $dirAbs = $root . '/storage/fotos';
  if (!is_dir($dirAbs)) { @mkdir($dirAbs, 0777, true); }

  // Normalizamos a JPG para evitar sorpresas
  $destAbs = $dirAbs . '/doc_' . $idUsuario . '.jpg';
  $imgData = file_get_contents($file['tmp_name']);
  $im = @imagecreatefromstring($imgData);
  if (!$im) { http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'Imagen inválida']); exit; }
  imagejpeg($im, $destAbs, 90);
  imagedestroy($im);

  // URL pública que usarás en el front
  $url = '/siged/storage/fotos/doc_' . $idUsuario . '.jpg';

  // Guardar en BD (opcional pero útil para reutilizar la ruta)
  $pdo = DB::conn();
  $st = $pdo->prepare("UPDATE dbo.USUARIOS SET RUTA_FOTO = :u WHERE ID_USUARIO = :id");
  $st->execute([':u' => $url, ':id' => $idUsuario]);

  echo json_encode(['ok'=>true, 'url'=>$url]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
