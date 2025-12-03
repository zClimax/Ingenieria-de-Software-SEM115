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
$idUsr   = (int)($u['id'] ?? $u['ID_USUARIO'] ?? 0);
$depJefe = (int)($u['id_departamento'] ?? $u['ID_DEPARTAMENTO'] ?? 0);
if ($depJefe <= 0 && $idUsr > 0) {
  $q = $pdo->prepare("SELECT ID_DEPARTAMENTO FROM dbo.USUARIOS WHERE ID_USUARIO=:u");
  $q->execute([':u' => $idUsr]);
  $depJefe = (int)($q->fetchColumn() ?: 0);
}

$id       = (int)($_POST['id']      ?? 0);
$idCurr   = (int)($_POST['id_curr'] ?? 0);
$tipoPart = trim((string)($_POST['tipo_part']    ?? ''));
$nomProg  = trim((string)($_POST['nombre_prog']  ?? ''));
$periodo  = trim((string)($_POST['periodo']      ?? ''));

if ($id <= 0) {
  http_response_code(400);
  exit('ID de solicitud inválido');
}

// Validar solicitud CPPL + depto
$qSol = $pdo->prepare("
  SELECT ID_DEPARTAMENTO_APROBADOR, TIPO_DOCUMENTO, ESTADO
  FROM dbo.SOLICITUD_DOCUMENTO
  WHERE ID_SOLICITUD = :id
");
$qSol->execute([':id' => $id]);
$S = $qSol->fetch(PDO::FETCH_ASSOC);

if (!$S || strtoupper((string)$S['TIPO_DOCUMENTO']) !== 'CPPL') {
  http_response_code(403);
  exit('Solicitud inválida o no CPPL.');
}
if ((int)$S['ID_DEPARTAMENTO_APROBADOR'] !== $depJefe) {
  http_response_code(403);
  exit('No autorizado: no eres el jefe del departamento aprobador.');
}

// Control de estado
$estado          = (string)$S['ESTADO'];
$estadosEditables = ['ENVIADA', 'APROBADA'];
if (!in_array($estado, $estadosEditables, true)) {
  header('Location: /siged/public/index.php?action=jefe_ver&id='.$id.
         '&msg=No%20editable%20en%20'.$estado);
  exit;
}

// Validar campos
if ($tipoPart === '' || $nomProg === '' || $periodo === '') {
  header('Location: /siged/public/index.php?action=jefe_ver&id='.$id.'&cppl_err=campos');
  exit;
}

// Upsert por ID_SOLICITUD
$exSt = $pdo->prepare("
  SELECT ID_CURR
  FROM dbo.DOC_DESARROLLO_CURRICULAR
  WHERE ID_SOLICITUD = :sid
");
$exSt->execute([':sid' => $id]);
$exId = (int)($exSt->fetchColumn() ?: 0);

if ($exId > 0) {
  $up = $pdo->prepare("
    UPDATE dbo.DOC_DESARROLLO_CURRICULAR
    SET TIPO_PART   = :tp,
        NOMBRE_PROG = :np,
        PERIODO     = :per
    WHERE ID_SOLICITUD = :sid
  ");
  $up->execute([
    ':tp'  => $tipoPart,
    ':np'  => $nomProg,
    ':per' => $periodo,
    ':sid' => $id,
  ]);
} else {
  $ins = $pdo->prepare("
    INSERT INTO dbo.DOC_DESARROLLO_CURRICULAR
      (ID_SOLICITUD, TIPO_PART, NOMBRE_PROG, PERIODO)
    VALUES
      (:sid, :tp, :np, :per)
  ");
  $ins->execute([
    ':sid' => $id,
    ':tp'  => $tipoPart,
    ':np'  => $nomProg,
    ':per' => $periodo,
  ]);
}

header('Location: /siged/public/index.php?action=jefe_ver&id='.$id.'&cppl_saved=1');
exit;
