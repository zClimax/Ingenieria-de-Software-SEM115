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

$id        = (int)($_POST['id']       ?? 0);
$idConst   = (int)($_POST['id_const'] ?? 0);
$programa  = trim((string)($_POST['programa']       ?? ''));
$modulos   = trim((string)($_POST['nombre_modulos'] ?? ''));
$fEmision  = trim((string)($_POST['fecha_emision']  ?? ''));

if ($id <= 0) {
  http_response_code(400);
  exit('ID de solicitud inválido');
}

// Validar solicitud CMEL + depto
$qSol = $pdo->prepare("
  SELECT ID_DEPARTAMENTO_APROBADOR, TIPO_DOCUMENTO, ESTADO
  FROM dbo.SOLICITUD_DOCUMENTO
  WHERE ID_SOLICITUD = :id
");
$qSol->execute([':id' => $id]);
$S = $qSol->fetch(PDO::FETCH_ASSOC);

if (!$S || strtoupper((string)$S['TIPO_DOCUMENTO']) !== 'CMEL') {
  http_response_code(403);
  exit('Solicitud inválida o no CMEL.');
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

// Normalizar fecha de emisión
if ($fEmision === '') {
  $fEmision = date('Y-m-d');
} else {
  try {
    $fEmision = (new DateTime($fEmision))->format('Y-m-d');
  } catch (\Throwable $e) {
    $fEmision = date('Y-m-d');
  }
}

// Validar campos
if ($programa === '' || $modulos === '') {
  header('Location: /siged/public/index.php?action=jefe_ver&id='.$id.'&cmel_err=campos');
  exit;
}

// Upsert por ID_SOLICITUD
$exSt = $pdo->prepare("
  SELECT ID_CONST
  FROM dbo.DOC_CONST_MOD_ESP_LIC
  WHERE ID_SOLICITUD = :sid
");
$exSt->execute([':sid' => $id]);
$exId = (int)($exSt->fetchColumn() ?: 0);

if ($exId > 0) {
  $up = $pdo->prepare("
    UPDATE dbo.DOC_CONST_MOD_ESP_LIC
    SET PROGRAMA       = :p,
        NOMBRE_MODULOS = :m,
        FECHA_EMISION  = :fe
    WHERE ID_SOLICITUD = :sid
  ");
  $up->execute([
    ':p'   => $programa,
    ':m'   => $modulos,
    ':fe'  => $fEmision,
    ':sid' => $id,
  ]);
} else {
  $ins = $pdo->prepare("
    INSERT INTO dbo.DOC_CONST_MOD_ESP_LIC
      (ID_SOLICITUD, PROGRAMA, NOMBRE_MODULOS, FECHA_EMISION)
    VALUES
      (:sid, :p, :m, :fe)
  ");
  $ins->execute([
    ':sid' => $id,
    ':p'   => $programa,
    ':m'   => $modulos,
    ':fe'  => $fEmision,
  ]);
}

header('Location: /siged/public/index.php?action=jefe_ver&id='.$id.'&cmel_saved=1');
exit;
