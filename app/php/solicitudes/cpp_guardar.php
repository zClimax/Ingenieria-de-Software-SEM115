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

$sid       = (int)($_POST['id'] ?? 0);
$idProj    = (int)($_POST['id_proyecto'] ?? 0);

if ($sid <= 0) {
  http_response_code(400);
  exit('ID de solicitud inválido');
}

// Validar solicitud CPP + depto
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
if (strtoupper((string)$S['TIPO_DOCUMENTO']) !== 'CPP') {
  http_response_code(403);
  exit('La solicitud no es de tipo CPP');
}

$depApr = (int)($S['ID_DEPARTAMENTO_APROBADOR'] ?? 0);
if ($depApr === 0 || $depApr !== $miDep) {
  http_response_code(403);
  exit('No tienes permisos para capturar datos de esta solicitud');
}

// Datos del formulario
$nombreProyecto = trim((string)($_POST['nombre_proyecto'] ?? ''));
$lugarObtenido  = trim((string)($_POST['lugar_obtenido'] ?? ''));
$nombreConcurso = trim((string)($_POST['nombre_concurso'] ?? ''));

if ($nombreProyecto === '' || $lugarObtenido === '' || $nombreConcurso === '') {
  header('Location: /siged/public/index.php?action=jefe_ver&id='.$sid.'&cpp_err=campos');
  exit;
}

// Upsert por ID_SOLICITUD
$exSt = $pdo->prepare("
  SELECT ID_PROYECTO_PREMIADO
  FROM dbo.DOCENTE_PROYECTO_PREMIADO
  WHERE ID_SOLICITUD = :sid
");
$exSt->execute([':sid' => $sid]);
$exId = (int)($exSt->fetchColumn() ?: 0);

if ($exId > 0) {
  $up = $pdo->prepare("
    UPDATE dbo.DOCENTE_PROYECTO_PREMIADO
    SET NOMBRE_PROYECTO = :nom,
        LUGAR_OBTENIDO  = :lug,
        NOMBRE_CONCURSO = :con
    WHERE ID_SOLICITUD = :sid
  ");
  $up->execute([
    ':nom' => $nombreProyecto,
    ':lug' => $lugarObtenido,
    ':con' => $nombreConcurso,
    ':sid' => $sid,
  ]);
} else {
  $ins = $pdo->prepare("
    INSERT INTO dbo.DOCENTE_PROYECTO_PREMIADO
      (ID_SOLICITUD, NOMBRE_PROYECTO, LUGAR_OBTENIDO, NOMBRE_CONCURSO)
    VALUES
      (:sid, :nom, :lug, :con)
  ");
  $ins->execute([
    ':sid' => $sid,
    ':nom' => $nombreProyecto,
    ':lug' => $lugarObtenido,
    ':con' => $nombreConcurso,
  ]);
}

header('Location: /siged/public/index.php?action=jefe_ver&id='.$sid.'&cpp_saved=1');
exit;
