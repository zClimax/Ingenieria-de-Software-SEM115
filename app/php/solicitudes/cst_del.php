<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';

Session::start();
requireRole(['JEFE_DEPARTAMENTO']);

$pdo = DB::conn();
$u   = Session::user() ?: [];

// Depto del jefe
$miDep = 0;
if (!empty($u['id_departamento']) && (int)$u['id_departamento'] > 0) {
  $miDep = (int)$u['id_departamento'];
} else {
  $q = $pdo->prepare("SELECT ID_DEPARTAMENTO FROM dbo.USUARIOS WHERE ID_USUARIO=:idu");
  $q->execute([':idu' => (int)$u['id']]);
  $miDep = (int)($q->fetchColumn() ?: 0);
}

$sid        = (int)($_POST['id'] ?? 0);
$idSinodalia= (int)($_POST['sinodalia_id'] ?? 0);

if ($sid <= 0 || $idSinodalia <= 0) {
  http_response_code(400);
  exit('Parámetros inválidos');
}

// Validar solicitud CST + depto
$st = $pdo->prepare("
  SELECT TIPO_DOCUMENTO, ID_DEPARTAMENTO_APROBADOR
  FROM dbo.SOLICITUD_DOCUMENTO
  WHERE ID_SOLICITUD = :sid
");
$st->execute([':sid' => $sid]);
$S = $st->fetch(PDO::FETCH_ASSOC);

if (!$S || strtoupper((string)$S['TIPO_DOCUMENTO']) !== 'CST') {
  http_response_code(403);
  exit('Solicitud inválida o no CST');
}
$depApr = (int)($S['ID_DEPARTAMENTO_APROBADOR'] ?? 0);
if ($depApr === 0 || $depApr !== $miDep) {
  http_response_code(403);
  exit('No tienes permisos sobre esta solicitud');
}

// Verificar que la sinodalía pertenece a la solicitud
$chk = $pdo->prepare("
  SELECT 1
  FROM dbo.DOCENTE_SINODALIA_TITULACION
  WHERE ID_SINODALIA=:idc AND ID_SOLICITUD=:sid
");
$chk->execute([':idc' => $idSinodalia, ':sid' => $sid]);
if (!$chk->fetchColumn()) {
  http_response_code(404);
  exit('Registro de sinodalía no encontrado');
}

$del = $pdo->prepare("
  DELETE FROM dbo.DOCENTE_SINODALIA_TITULACION
  WHERE ID_SINODALIA=:idc
");
$del->execute([':idc' => $idSinodalia]);

header('Location: /siged/public/index.php?action=jefe_ver&id='.$sid.'&cst_saved=1');
exit;
