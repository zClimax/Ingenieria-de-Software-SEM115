<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';

Session::start();

// Permitimos DOCENTE y JEFE
requireRole(['DOCENTE', 'JEFE_DEPARTAMENTO']);

$pdo = DB::conn();
$u   = Session::user();
$uid = (int)($u['id'] ?? 0);
$rol = strtoupper((string)($u['rol'] ?? ''));

$idEv = (int)($_GET['id'] ?? 0);
if ($idEv <= 0) {
    http_response_code(400);
    exit('ID inválido');
}

// Armamos el SQL según el rol
if ($rol === 'DOCENTE') {
    $sql = "
        SELECT E.ID_EVIDENCIA, E.NOMBRE_ARCHIVO, E.RUTA_SISTEMA
        FROM dbo.TICKET_EVIDENCIA E
        JOIN dbo.TICKETS T ON T.ID_TICKET = E.ID_TICKET
        JOIN dbo.DOCENTE D ON D.ID_DOCENTE = T.ID_DOCENTE_GENERADOR
        WHERE E.ID_EVIDENCIA = :idEv
          AND D.ID_USUARIO = :uid
    ";
} else {
    // JEFE_DEPARTAMENTO: sólo si es responsable del ticket
    $sql = "
        SELECT E.ID_EVIDENCIA, E.NOMBRE_ARCHIVO, E.RUTA_SISTEMA
        FROM dbo.TICKET_EVIDENCIA E
        JOIN dbo.TICKETS T ON T.ID_TICKET = E.ID_TICKET
        WHERE E.ID_EVIDENCIA = :idEv
          AND T.ID_USUARIO_RESPONSABLE = :uid
    ";
}

$st = $pdo->prepare($sql);
$st->execute([':idEv' => $idEv, ':uid' => $uid]);
$row = $st->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(403);
    exit('No tienes permiso para descargar esta evidencia');
}

$ruta = (string)$row['RUTA_SISTEMA'];

if (!is_file($ruta)) {
    http_response_code(404);
    exit('Archivo no encontrado en el servidor');
}

// Descarga
$nombreOriginal = $row['NOMBRE_ARCHIVO'] ?: basename($ruta);

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($nombreOriginal) . '"');
header('Content-Length: ' . filesize($ruta));

readfile($ruta);
exit;
