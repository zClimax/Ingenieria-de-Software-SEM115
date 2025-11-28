<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';

Session::start();
requireRole(['DOCENTE']);

$pdo  = DB::conn();
$user = Session::user();
$uid  = (int)($user['id'] ?? 0);

$idTicket = (int)($_POST['id'] ?? 0);
if ($idTicket <= 0) { die('ID de ticket inválido'); }

// 1. Validar que el ticket es del docente y NO está cerrado
$sql = "
  SELECT T.ID_TICKET, T.ESTATUS
  FROM dbo.TICKETS T
  JOIN dbo.DOCENTE D ON D.ID_DOCENTE = T.ID_DOCENTE_GENERADOR
  JOIN dbo.USUARIOS U ON U.ID_USUARIO = D.ID_USUARIO
  WHERE T.ID_TICKET = :id
    AND U.ID_USUARIO = :u
";
$st = $pdo->prepare($sql);
$st->execute([':id' => $idTicket, ':u' => $uid]);
$row = $st->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    die('Ticket no encontrado o sin permiso.');
}

if (strtoupper((string)$row['ESTATUS']) === 'CERRADO') {
    die('No puedes subir evidencias a un ticket cerrado.');
}

// 2. Validar archivo
if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
    die('Archivo requerido.');
}
$f = $_FILES['archivo'];

$ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
$allowed = ['pdf','jpg','jpeg','png'];
if (!in_array($ext, $allowed, true)) { die('Tipo no permitido.'); }
if ($f['size'] > 5*1024*1024) { die('> 5 MB'); }

// 3. Carpeta destino (puedes usar la misma de solicitudes)
$baseDirPath = __DIR__ . '/../../uploads/evidencias';
if (!is_dir($baseDirPath)) {
    if (!mkdir($baseDirPath, 0775, true) && !is_dir($baseDirPath)) {
        die('No se pudo crear el directorio de evidencias.');
    }
}
$baseDir = realpath($baseDirPath);
if (!$baseDir) { die('No se pudo resolver la ruta de evidencias.'); }

$random = bin2hex(random_bytes(8)) . '.' . $ext;
$dest   = $baseDir . DIRECTORY_SEPARATOR . $random;

if (!move_uploaded_file($f['tmp_name'], $dest)) { die('No se pudo guardar el archivo.'); }

// 4. Insertar en TICKET_EVIDENCIA
$TE = Config::MAP['TKEVID'];

$sqlIns = "INSERT INTO {$TE['TABLE']}
           ({$TE['TKT']}, {$TE['NOM']}, {$TE['RUTA']}, {$TE['MIME']}, {$TE['BYTES']})
           VALUES (:t, :nom, :ruta, :mime, :bytes)";
$ins = $pdo->prepare($sqlIns);
$ins->execute([
    ':t'    => $idTicket,
    ':nom'  => $f['name'],
    ':ruta' => $dest,
    ':mime' => ($f['type'] ?? null),
    ':bytes'=> $f['size'],
]);

header('Location: /SIGED/public/index.php?action=tk_ver&id='.$idTicket);
exit;
