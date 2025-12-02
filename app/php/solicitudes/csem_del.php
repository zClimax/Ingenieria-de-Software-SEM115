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

$sid     = (int)($_POST['id'] ?? 0);
$idCarga = (int)($_POST['carga_id'] ?? 0);

if ($sid <= 0 || $idCarga <= 0) {
  http_response_code(400);
  exit('Parámetros inválidos');
}

// Validar solicitud CSEM + depto
$st = $pdo->prepare("
  SELECT TIPO_DOCUMENTO, ID_DEPARTAMENTO_APROBADOR
  FROM dbo.SOLICITUD_DOCUMENTO
  WHERE ID_SOLICITUD = :sid
");
$st->execute([':sid' => $sid]);
$S = $st->fetch(PDO::FETCH_ASSOC);

if (!$S || strtoupper((string)$S['TIPO_DOCUMENTO']) !== 'CSEM') {
  http_response_code(403);
  exit('Solicitud inválida o no CSEM');
}
$depApr = (int)($S['ID_DEPARTAMENTO_APROBADOR'] ?? 0);
if ($depApr === 0 || $depApr !== $miDep) {
  http_response_code(403);
  exit('No tienes permisos sobre esta solicitud');
}

// Verificar que la carga pertenece a la solicitud
$chk = $pdo->prepare("
  SELECT 1 FROM dbo.DOCENTE_CARGA_MODALIDAD
  WHERE ID_CARGA_MODALIDAD=:idc AND ID_SOLICITUD=:sid
");
$chk->execute([':idc' => $idCarga, ':sid' => $sid]);
if (!$chk->fetchColumn()) {
  http_response_code(404);
  exit('Registro de carga no encontrado');
}

$del = $pdo->prepare("DELETE FROM dbo.DOCENTE_CARGA_MODALIDAD WHERE ID_CARGA_MODALIDAD=:idc");
$del->execute([':idc' => $idCarga]);

header('Location: /siged/public/index.php?action=jefe_ver&id='.$sid.'&csem_saved=1');
exit;
