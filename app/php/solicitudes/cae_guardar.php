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

$sid    = (int)($_POST['id'] ?? 0);
$idActa = (int)($_POST['id_acta_examen'] ?? 0);

if ($sid <= 0) {
  http_response_code(400);
  exit('ID de solicitud inválido');
}

// Validar solicitud CAE + depto
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
if (strtoupper((string)$S['TIPO_DOCUMENTO']) !== 'CAE') {
  http_response_code(403);
  exit('La solicitud no es de tipo CAE');
}

$depApr = (int)($S['ID_DEPARTAMENTO_APROBADOR'] ?? 0);
if ($depApr === 0 || $depApr !== $miDep) {
  http_response_code(403);
  exit('No tienes permisos para capturar datos de esta solicitud');
}

// Datos del formulario
$tipoExamen       = trim((string)($_POST['tipo_examen'] ?? ''));
$fechaExamen      = trim((string)($_POST['fecha_examen'] ?? ''));
$nombreEstudiante = trim((string)($_POST['nombre_estudiante'] ?? ''));
$programa         = trim((string)($_POST['programa'] ?? ''));
$rolPart          = trim((string)($_POST['rol_participacion'] ?? ''));

if ($tipoExamen === '' || $fechaExamen === '' || $nombreEstudiante === '' ||
    $programa === '' || $rolPart === '') {
  header('Location: /siged/public/index.php?action=jefe_ver&id='.$sid.'&cae_err=campos');
  exit;
}

// Upsert
$exSt = $pdo->prepare("
  SELECT ID_ACTA_EXAMEN
  FROM dbo.DOCENTE_ACTA_EXAMEN
  WHERE ID_SOLICITUD = :sid
");
$exSt->execute([':sid' => $sid]);
$exId = (int)($exSt->fetchColumn() ?: 0);

if ($exId > 0) {
  $up = $pdo->prepare("
    UPDATE dbo.DOCENTE_ACTA_EXAMEN
    SET TIPO_EXAMEN       = :tip,
        FECHA_EXAMEN      = :fec,
        NOMBRE_ESTUDIANTE = :est,
        PROGRAMA          = :pro,
        ROL_PARTICIPACION = :rol
    WHERE ID_SOLICITUD    = :sid
  ");
  $up->execute([
    ':tip' => $tipoExamen,
    ':fec' => $fechaExamen,
    ':est' => $nombreEstudiante,
    ':pro' => $programa,
    ':rol' => $rolPart,
    ':sid' => $sid,
  ]);
} else {
  $ins = $pdo->prepare("
    INSERT INTO dbo.DOCENTE_ACTA_EXAMEN
      (ID_SOLICITUD, TIPO_EXAMEN, FECHA_EXAMEN, NOMBRE_ESTUDIANTE, PROGRAMA, ROL_PARTICIPACION)
    VALUES
      (:sid, :tip, :fec, :est, :pro, :rol)
  ");
  $ins->execute([
    ':sid' => $sid,
    ':tip' => $tipoExamen,
    ':fec' => $fechaExamen,
    ':est' => $nombreEstudiante,
    ':pro' => $programa,
    ':rol' => $rolPart,
  ]);
}

header('Location: /siged/public/index.php?action=jefe_ver&id='.$sid.'&cae_saved=1');
exit;
