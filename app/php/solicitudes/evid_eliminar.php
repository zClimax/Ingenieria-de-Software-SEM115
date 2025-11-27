<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';

Session::start();
requireRole(['DOCENTE']);
header('Content-Type: application/json'); // Importante para la respuesta AJAX

// Solo aceptamos POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
    exit;
}

$pdo = DB::conn();
$user = Session::user();
$uid = (int)($user['id'] ?? 0);
$idEvidencia = (int)($_POST['id'] ?? 0);

if ($idEvidencia <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'ID de evidencia inválido']);
    exit;
}

// Configuración de tablas
$E = Config::MAP['EVID'];
$S = Config::MAP['SOLICITUD'];
$D = Config::MAP['DOCENTE'];

// 1. Verificar que la evidencia existe, pertenece al docente y está en BORRADOR
$sql = "
    SELECT E.{$E['ID']} as id, E.{$E['RUTA']} as ruta, S.{$S['ESTADO']} as estado
    FROM {$E['TABLE']} E
    JOIN {$S['TABLE']} S ON S.{$S['ID']} = E.{$E['SOL']}
    JOIN {$D['TABLE']} D ON D.{$D['ID']} = S.{$S['DOC']}
    WHERE E.{$E['ID']} = :idEv AND D.{$D['ID_USR']} = :uid
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':idEv' => $idEvidencia, ':uid' => $uid]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo json_encode(['ok' => false, 'msg' => 'Evidencia no encontrada o sin permiso']);
    exit;
}

if ($row['estado'] !== 'BORRADOR') {
    echo json_encode(['ok' => false, 'msg' => 'No se puede eliminar: Solicitud bloqueada (no es borrador)']);
    exit;
}

// 2. Eliminar archivo físico
$rutaArchivo = $row['ruta'];
if (file_exists($rutaArchivo)) {
    if (!unlink($rutaArchivo)) {
        // Opcional: Log de error, pero permitimos borrar el registro de BD si el archivo ya no estaba
        // error_log("No se pudo borrar archivo: $rutaArchivo");
    }
}

// 3. Eliminar registro en BD
$del = $pdo->prepare("DELETE FROM {$E['TABLE']} WHERE {$E['ID']} = :id");
$del->execute([':id' => $idEvidencia]);

echo json_encode(['ok' => true]);