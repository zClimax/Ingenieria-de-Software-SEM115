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
$idCurso  = (int)($_POST['id_curso_impartido'] ?? 0);

if ($sid <= 0) {
  http_response_code(400);
  exit('ID de solicitud inválido');
}

// Validar solicitud CCUI + depto
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
if (strtoupper((string)$S['TIPO_DOCUMENTO']) !== 'CCUI') {
  http_response_code(403);
  exit('La solicitud no es de tipo CCUI');
}

$depApr = (int)($S['ID_DEPARTAMENTO_APROBADOR'] ?? 0);
if ($depApr === 0 || $depApr !== $miDep) {
  http_response_code(403);
  exit('No tienes permisos para capturar datos de esta solicitud');
}

// Datos del formulario
$nombreCurso = trim((string)($_POST['nombre_curso'] ?? ''));
$numRegistro = trim((string)($_POST['numero_registro'] ?? ''));
$fIni        = trim((string)($_POST['fecha_inicio'] ?? ''));
$fFin        = trim((string)($_POST['fecha_fin'] ?? ''));
$numHoras    = (int)($_POST['numero_horas'] ?? 0);

if ($nombreCurso === '' || $numRegistro === '' || $fIni === '' || $fFin === '') {
  header('Location: /siged/public/index.php?action=jefe_ver&id='.$sid.'&ccui_err=campos');
  exit;
}

// Regla de negocio: mínimo 30 horas
if ($numHoras < 30) {
  header('Location: /siged/public/index.php?action=jefe_ver&id='.$sid.'&ccui_err=horas');
  exit;
}

// Verificar si ya existe registro
$exSt = $pdo->prepare("
  SELECT ID_CURSO_IMPARTIDO
  FROM dbo.DOCENTE_CURSO_IMPARTIDO
  WHERE ID_SOLICITUD = :sid
");
$exSt->execute([':sid' => $sid]);
$exId = (int)($exSt->fetchColumn() ?: 0);

if ($exId > 0) {
  $up = $pdo->prepare("
    UPDATE dbo.DOCENTE_CURSO_IMPARTIDO
    SET NOMBRE_CURSO    = :nom,
        NUMERO_REGISTRO = :reg,
        FECHA_INICIO    = :fini,
        FECHA_FIN       = :ffin,
        NUMERO_HORAS    = :hrs
    WHERE ID_SOLICITUD  = :sid
  ");
  $up->execute([
    ':nom'  => $nombreCurso,
    ':reg'  => $numRegistro,
    ':fini' => $fIni,
    ':ffin' => $fFin,
    ':hrs'  => $numHoras,
    ':sid'  => $sid,
  ]);
} else {
  $ins = $pdo->prepare("
    INSERT INTO dbo.DOCENTE_CURSO_IMPARTIDO
      (ID_SOLICITUD, NOMBRE_CURSO, NUMERO_REGISTRO, FECHA_INICIO, FECHA_FIN, NUMERO_HORAS)
    VALUES
      (:sid, :nom, :reg, :fini, :ffin, :hrs)
  ");
  $ins->execute([
    ':sid'  => $sid,
    ':nom'  => $nombreCurso,
    ':reg'  => $numRegistro,
    ':fini' => $fIni,
    ':ffin' => $fFin,
    ':hrs'  => $numHoras,
  ]);
}

header('Location: /siged/public/index.php?action=jefe_ver&id='.$sid.'&ccui_saved=1');
exit;
