<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';

Session::start();
requireRole(['JEFE_DEPARTAMENTO']); // o el rol académico que uses

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

$sid      = (int)($_POST['id'] ?? 0);
$idMat    = (int)($_POST['id_material'] ?? 0);

if ($sid <= 0) {
  http_response_code(400);
  exit('ID de solicitud inválido');
}

// Validar solicitud CMDI + depto
$st = $pdo->prepare("
  SELECT ID_SOLICITUD, TIPO_DOCUMENTO, ID_DEPARTAMENTO_APROBADOR
  FROM dbo.SOLICITUD_DOCUMENTO
  WHERE ID_SOLICITUD = :sid
");
$st->execute([':sid' => $sid]);
$S = $st->fetch(PDO::FETCH_ASSOC);

if (!$S) {
  http_response_code(404);
  exit('Solicitud no encontrada');
}
if (strtoupper((string)$S['TIPO_DOCUMENTO']) !== 'CMDI') {
  http_response_code(403);
  exit('La solicitud no es de tipo CMDI');
}

$depApr = (int)($S['ID_DEPARTAMENTO_APROBADOR'] ?? 0);
if ($depApr === 0 || $depApr !== $miDep) {
  http_response_code(403);
  exit('No tienes permisos para capturar datos de esta solicitud');
}

// Datos del formulario
$enfoque = trim((string)($_POST['enfoque'] ?? ''));
$lista   = trim((string)($_POST['lista_productos'] ?? ''));
$impacto = trim((string)($_POST['descripcion_impacto'] ?? ''));

if ($enfoque === '' || $lista === '' || $impacto === '') {
  header('Location: /siged/public/index.php?action=jefe_ver&id='.$sid.'&cmdi_err=campos');
  exit;
}

// Verificar si ya existe registro
$exSt = $pdo->prepare("
  SELECT ID_MATERIAL
  FROM dbo.DOCENTE_MATERIALES_INCLUSIVOS
  WHERE ID_SOLICITUD = :sid
");
$exSt->execute([':sid' => $sid]);
$exId = (int)($exSt->fetchColumn() ?: 0);

if ($exId > 0) {
  $up = $pdo->prepare("
    UPDATE dbo.DOCENTE_MATERIALES_INCLUSIVOS
    SET ENFOQUE = :enf,
        LISTA_PRODUCTOS = :lst,
        DESCRIPCION_IMPACTO = :imp
    WHERE ID_SOLICITUD = :sid
  ");
  $up->execute([
    ':enf' => $enfoque,
    ':lst' => $lista,
    ':imp' => $impacto,
    ':sid' => $sid,
  ]);
} else {
  $ins = $pdo->prepare("
    INSERT INTO dbo.DOCENTE_MATERIALES_INCLUSIVOS
      (ID_SOLICITUD, ENFOQUE, LISTA_PRODUCTOS, DESCRIPCION_IMPACTO)
    VALUES
      (:sid, :enf, :lst, :imp)
  ");
  $ins->execute([
    ':sid' => $sid,
    ':enf' => $enfoque,
    ':lst' => $lista,
    ':imp' => $impacto,
  ]);
}

header('Location: /siged/public/index.php?action=jefe_ver&id='.$sid.'&cmdi_saved=1');
exit;
