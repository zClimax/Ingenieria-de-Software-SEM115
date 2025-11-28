<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';

Session::start();
requireRole(['DOCENTE']);
header('Content-Type: application/json');

$pdo  = DB::conn();
$user = Session::user();
$uid  = (int)($user['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
    exit;
}

$idEvidencia = (int)($_POST['id'] ?? 0);
if ($idEvidencia <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'ID de evidencia inválido']);
    exit;
}

$TE = Config::MAP['TKEVID'];

// 1. Validar que la evidencia le pertenece al docente y ticket no está cerrado
$sql = "
  SELECT E.{$TE['ID']} AS id,
         E.{$TE['RUTA']} AS ruta,
         T.ESTATUS
  FROM {$TE['TABLE']} E
  JOIN dbo.TICKETS T ON T.ID_TICKET = E.{$TE['TKT']}
  JOIN dbo.DOCENTE D ON D.ID_DOCENTE = T.ID_DOCENTE_GENERADOR
  JOIN dbo.USUARIOS U ON U.ID_USUARIO = D.ID_USUARIO
  WHERE E.{$TE['ID']} = :idEv
    AND U.ID_USUARIO = :uid
";
$st = $pdo->prepare($sql);
$st->execute([':idEv' => $idEvidencia, ':uid' => $uid]);
$row = $st->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo json_encode(['ok' => false, 'msg' => 'Evidencia no encontrada o sin permiso']);
    exit;
}

if (strtoupper((string)$row['ESTATUS']) === 'CERRADO') {
    echo json_encode(['ok' => false, 'msg' => 'No se puede eliminar en un ticket cerrado']);
    exit;
}

$rutaArchivo = (string)$row['ruta'];
if ($rutaArchivo && file_exists($rutaArchivo)) {
    @unlink($rutaArchivo); // si falla, no reventamos
}

// 2. Borrar registro
$del = $pdo->prepare("DELETE FROM {$TE['TABLE']} WHERE {$TE['ID']} = :id");
$del->execute([':id' => $idEvidencia]);

echo json_encode(['ok' => true]);
