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

$sid      = (int)($_POST['id'] ?? 0);
$idCom    = (int)($_POST['id_comision'] ?? 0);

if ($sid <= 0) {
  http_response_code(400);
  exit('ID de solicitud inválido');
}

// Validar solicitud CCID + depto
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
if (strtoupper((string)$S['TIPO_DOCUMENTO']) !== 'CCID') {
  http_response_code(403);
  exit('La solicitud no es de tipo CCID');
}

$depApr = (int)($S['ID_DEPARTAMENTO_APROBADOR'] ?? 0);
if ($depApr === 0 || $depApr !== $miDep) {
  http_response_code(403);
  exit('No tienes permisos para capturar datos de esta solicitud');
}

// Datos del formulario
$nombreCurso = trim((string)($_POST['nombre_curso'] ?? ''));
$tipoCurso   = trim((string)($_POST['tipo_curso'] ?? ''));
$numHoras    = (int)($_POST['numero_horas'] ?? 0);
$numOficio   = trim((string)($_POST['numero_oficio'] ?? ''));

if ($nombreCurso === '' || $tipoCurso === '' || $numOficio === '') {
  header('Location: /siged/public/index.php?action=jefe_ver&id='.$sid.'&ccid_err=campos');
  exit;
}

// Regla de negocio: mínimo 30 horas
if ($numHoras < 30) {
  header('Location: /siged/public/index.php?action=jefe_ver&id='.$sid.'&ccid_err=horas');
  exit;
}

// Verificar si ya existe registro
$exSt = $pdo->prepare("
  SELECT ID_COMISION
  FROM dbo.DOCENTE_COMISION_INSTRUCTOR
  WHERE ID_SOLICITUD = :sid
");
$exSt->execute([':sid' => $sid]);
$exId = (int)($exSt->fetchColumn() ?: 0);

if ($exId > 0) {
  $up = $pdo->prepare("
    UPDATE dbo.DOCENTE_COMISION_INSTRUCTOR
    SET NOMBRE_CURSO = :nom,
        TIPO_CURSO   = :tip,
        NUMERO_HORAS = :hrs,
        NUMERO_OFICIO= :ofc
    WHERE ID_SOLICITUD = :sid
  ");
  $up->execute([
    ':nom' => $nombreCurso,
    ':tip' => $tipoCurso,
    ':hrs' => $numHoras,
    ':ofc' => $numOficio,
    ':sid' => $sid,
  ]);
} else {
  $ins = $pdo->prepare("
    INSERT INTO dbo.DOCENTE_COMISION_INSTRUCTOR
      (ID_SOLICITUD, NOMBRE_CURSO, TIPO_CURSO, NUMERO_HORAS, NUMERO_OFICIO)
    VALUES
      (:sid, :nom, :tip, :hrs, :ofc)
  ");
  $ins->execute([
    ':sid' => $sid,
    ':nom' => $nombreCurso,
    ':tip' => $tipoCurso,
    ':hrs' => $numHoras,
    ':ofc' => $numOficio,
  ]);
}

header('Location: /siged/public/index.php?action=jefe_ver&id='.$sid.'&ccid_saved=1');
exit;
