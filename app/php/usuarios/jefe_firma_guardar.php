<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';

Session::start();

// ====== Validar sesión ======
$u = (array)(Session::user() ?? []);
$idUser = (int)($u['id'] ?? 0);
if ($idUser <= 0) {
  http_response_code(403);
  echo "Sesión inválida"; exit;
}

try {
  $pdo = DB::conn();
} catch (Throwable $e) {
  http_response_code(500);
  echo "Sin conexión a BD"; exit;
}

// ====== Validar archivo ======
if (!isset($_FILES['firma']) || $_FILES['firma']['error'] !== UPLOAD_ERR_OK) {
  header('Location: /siged/public/index.php?action=home_jefe&msg=firma_error'); exit;
}

$tmp  = $_FILES['firma']['tmp_name'];
$size = (int)$_FILES['firma']['size'];
if ($size <= 0 || $size > 2*1024*1024) { // 2MB
  header('Location: /siged/public/index.php?action=home_jefe&msg=firma_pesada'); exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($tmp);
$okMime = in_array($mime, ['image/png','image/jpeg','image/jpg'], true);
if (!$okMime) {
  header('Location: /siged/public/index.php?action=home_jefe&msg=firma_tipo'); exit;
}

// ====== Destino en /storage/firmas (normaliza a PNG) ======
$baseDir = realpath(__DIR__ . '/../../..');              // .../SIGED
$destDir = $baseDir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'firmas';
if (!is_dir($destDir)) { @mkdir($destDir, 0775, true); }

$ts = date('Ymd_His');
$destPng = $destDir . DIRECTORY_SEPARATOR . "firma_{$idUser}_{$ts}.png";

// ====== Normalización a PNG ======
if ($mime === 'image/png') {
  $src = @imagecreatefrompng($tmp);
  if (!$src) { header('Location: /siged/public/index.php?action=home_jefe&msg=firma_error'); exit; }
  [$w,$h] = getimagesize($tmp);
  $maxW = 900;
  if ($w > $maxW) {
    $nw = $maxW; $nh = (int)round($h*$nw/$w);
    $dst = imagecreatetruecolor($nw,$nh);
    imagesavealpha($dst, true);
    $trans = imagecolorallocatealpha($dst, 0,0,0,127);
    imagefill($dst, 0,0, $trans);
    imagecopyresampled($dst,$src,0,0,0,0,$nw,$nh,$w,$h);
    imagepng($dst,$destPng,6);
    imagedestroy($dst);
  } else {
    imagesavealpha($src, true);
    imagepng($src,$destPng,6);
  }
  imagedestroy($src);
} else {
  // JPEG -> PNG con fondo blanco
  $src = @imagecreatefromjpeg($tmp);
  if (!$src) { header('Location: /siged/public/index.php?action=home_jefe&msg=firma_error'); exit; }
  [$w,$h] = getimagesize($tmp);
  $maxW = 900;
  $nw = ($w > $maxW) ? $maxW : $w;
  $nh = ($w > $maxW) ? (int)round($h*$nw/$w) : $h;
  $dst = imagecreatetruecolor($nw,$nh);
  $white = imagecolorallocate($dst, 255,255,255);
  imagefill($dst,0,0,$white);
  imagecopyresampled($dst,$src,0,0,0,0,$nw,$nh,$w,$h);
  imagepng($dst,$destPng,6);
  imagedestroy($src); imagedestroy($dst);
}

// ====== Ruta web y hash ======
$relWeb  = '/siged/storage/firmas/' . basename($destPng); // servir desde docroot
$hashBin = @hash_file('sha256', $destPng, true);
if ($hashBin === false) { $hashBin = ''; }

// ====== Persistencia: USUARIOS.RUTA_FIRMA / FIRMA_MIME / FIRMA_HASH / FECHA_FIRMA ======
$pdo->beginTransaction();

$sql = "
  UPDATE [SIGED].[dbo].[USUARIOS]
  SET RUTA_FIRMA = :ruta,
      FIRMA_MIME = :mime,
      FIRMA_HASH = :hash,
      FECHA_FIRMA = SYSDATETIME()
  WHERE ID_USUARIO = :id
";
$st = $pdo->prepare($sql);

// Bind estándar
$st->bindValue(':ruta', $relWeb, PDO::PARAM_STR);
$st->bindValue(':mime', 'image/png', PDO::PARAM_STR);
$st->bindValue(':id',   $idUser, PDO::PARAM_INT);

try {
  // Intento A: enviar hash como binario (PDO_SQLSRV)
  if (defined('PDO::SQLSRV_ENCODING_BINARY')) {
    // quinto parámetro = encoding binario (extensión del driver)
    $st->bindParam(':hash', $hashBin, PDO::PARAM_LOB, 0, PDO::SQLSRV_ENCODING_BINARY);
  } else {
    $st->bindParam(':hash', $hashBin, PDO::PARAM_LOB);
  }
  $st->execute();
} catch (Throwable $e) {
  // Fallback: usar literal 0xHEX (evita problemas de UCS-2)
  $pdo->rollBack();
  $pdo->beginTransaction();

  $hashHex = '0x' . bin2hex($hashBin);
  $sql2 = "
    UPDATE [SIGED].[dbo].[USUARIOS]
    SET RUTA_FIRMA = :ruta,
        FIRMA_MIME = :mime,
        FIRMA_HASH = $hashHex,
        FECHA_FIRMA = SYSDATETIME()
    WHERE ID_USUARIO = :id
  ";
  $st2 = $pdo->prepare($sql2);
  $st2->execute([
    ':ruta' => $relWeb,
    ':mime' => 'image/png',
    ':id'   => $idUser
  ]);
}

$pdo->commit();

// ====== Back to home con flash ======
header('Location: /siged/public/index.php?action=home_jefe&msg=firma_ok');
exit;
