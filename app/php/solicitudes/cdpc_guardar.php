<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';

Session::start();
requireRole(['JEFE_DEPARTAMENTO']);

$pdo = DB::conn();
$u   = Session::user() ?: [];

$sid = (int)($_POST['id'] ?? 0);
if ($sid <= 0) {
  http_response_code(400);
  exit('ID de solicitud inválido');
}

// Validar que la solicitud sea CDPC
$st = $pdo->prepare("
  SELECT ID_SOLICITUD, TIPO_DOCUMENTO
  FROM dbo.SOLICITUD_DOCUMENTO
  WHERE ID_SOLICITUD = :sid
");
$st->execute([':sid' => $sid]);
$S = $st->fetch(PDO::FETCH_ASSOC);

if (!$S) {
  http_response_code(404);
  exit('Solicitud no encontrada');
}
if (strtoupper((string)$S['TIPO_DOCUMENTO']) !== 'CDPC') {
  http_response_code(403);
  exit('La solicitud no es de tipo CDPC');
}

/*
  Actualmente no hay datos adicionales que guardar para CDPC.
  Si en el futuro agregas campos (p.ej. periodo, sede, generación),
  aquí iría el INSERT/UPDATE a la tabla correspondiente.
*/

header('Location: /siged/public/index.php?action=jefe_ver&id='.$sid.'&cdpc_saved=1');
exit;
