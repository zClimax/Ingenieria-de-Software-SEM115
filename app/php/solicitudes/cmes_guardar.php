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

$id        = (int)($_POST['id']          ?? 0);
$idCom     = (int)($_POST['id_comision'] ?? 0);
$programa  = trim((string)($_POST['programa']      ?? ''));
$fiInput   = trim((string)($_POST['fecha_inicio']  ?? ''));
$ffInput   = trim((string)($_POST['fecha_fin']     ?? ''));

if ($id <= 0) {
  http_response_code(400);
  exit('ID de solicitud inválido');
}

// Validar solicitud CMES + depto
$qSol = $pdo->prepare("
  SELECT ID_DEPARTAMENTO_APROBADOR, TIPO_DOCUMENTO, ESTADO
  FROM dbo.SOLICITUD_DOCUMENTO
  WHERE ID_SOLICITUD = :id
");
$qSol->execute([':id' => $id]);
$S = $qSol->fetch(PDO::FETCH_ASSOC);

if (!$S || strtoupper((string)$S['TIPO_DOCUMENTO']) !== 'CMES') {
  http_response_code(403);
  exit('Solicitud inválida o no CMES.');
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

// Normalizar fechas
try {
  $fi = (new DateTime($fiInput))->format('Y-m-d');
} catch (\Throwable $e) {
  $fi = '';
}
try {
  $ff = (new DateTime($ffInput))->format('Y-m-d');
} catch (\Throwable $e) {
  $ff = '';
}

// Validar campos
if ($programa === '' || $fi === '' || $ff === '') {
  header('Location: /siged/public/index.php?action=jefe_ver&id='.$id.'&cmes_err=campos');
  exit;
}

// (Opcional) Validar que fin >= inicio
if (strtotime($ff) < strtotime($fi)) {
  header('Location: /siged/public/index.php?action=jefe_ver&id='.$id.'&cmes_err=campos');
  exit;
}

// Upsert por ID_SOLICITUD
$exSt = $pdo->prepare("
  SELECT ID_COMISION
  FROM dbo.DOC_COMISION_MOD_ESP
  WHERE ID_SOLICITUD = :sid
");
$exSt->execute([':sid' => $id]);
$exId = (int)($exSt->fetchColumn() ?: 0);

if ($exId > 0) {
  $up = $pdo->prepare("
    UPDATE dbo.DOC_COMISION_MOD_ESP
    SET PROGRAMA     = :p,
        FECHA_INICIO = :fi,
        FECHA_FIN    = :ff
    WHERE ID_SOLICITUD = :sid
  ");
  $up->execute([
    ':p'   => $programa,
    ':fi'  => $fi,
    ':ff'  => $ff,
    ':sid' => $id,
  ]);
} else {
  $ins = $pdo->prepare("
    INSERT INTO dbo.DOC_COMISION_MOD_ESP
      (ID_SOLICITUD, PROGRAMA, FECHA_INICIO, FECHA_FIN)
    VALUES
      (:sid, :p, :fi, :ff)
  ");
  $ins->execute([
    ':sid' => $id,
    ':p'   => $programa,
    ':fi'  => $fi,
    ':ff'  => $ff,
  ]);
}

header('Location: /siged/public/index.php?action=jefe_ver&id='.$id.'&cmes_saved=1');
exit;
