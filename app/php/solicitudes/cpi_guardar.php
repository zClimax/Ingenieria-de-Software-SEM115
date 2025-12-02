<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';

Session::start();
requireRole(['JEFE_DEPARTAMENTO']); // o el rol que use el jefe académico

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
$idProyecto = (int)($_POST['id_proyecto'] ?? 0);

if ($sid <= 0) {
  http_response_code(400);
  exit('ID de solicitud inválido');
}

// Validar solicitud CPI + depto aprobador
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
if (strtoupper((string)$S['TIPO_DOCUMENTO']) !== 'CPI') {
  http_response_code(403);
  exit('La solicitud no es de tipo CPI');
}

$depApr = (int)($S['ID_DEPARTAMENTO_APROBADOR'] ?? 0);
if ($depApr === 0 || $depApr !== $miDep) {
  http_response_code(403);
  exit('No tienes permisos para capturar datos de esta solicitud');
}

// Datos del formulario
$nombreProyecto = trim((string)($_POST['nombre_proyecto'] ?? ''));
$listaAsig      = trim((string)($_POST['lista_asignaturas'] ?? ''));
$nivel          = trim((string)($_POST['nivel'] ?? ''));

if ($nombreProyecto === '' || $listaAsig === '') {
  header('Location: /siged/public/index.php?action=jefe_ver&id='.$sid.'&cpi_err=campos');
  exit;
}

// Verificar si ya existe registro
$exSt = $pdo->prepare("
  SELECT ID_PROYECTO
  FROM dbo.DOCENTE_PROYECTO_INTEGRADOR
  WHERE ID_SOLICITUD = :sid
");
$exSt->execute([':sid' => $sid]);
$exId = (int)($exSt->fetchColumn() ?: 0);

if ($exId > 0) {
  // Update
  $up = $pdo->prepare("
    UPDATE dbo.DOCENTE_PROYECTO_INTEGRADOR
    SET NOMBRE_PROYECTO = :nom,
        LISTA_ASIGNATURAS = :lst,
        NIVEL = :niv
    WHERE ID_SOLICITUD = :sid
  ");
  $up->execute([
    ':nom' => $nombreProyecto,
    ':lst' => $listaAsig,
    ':niv' => $nivel,
    ':sid' => $sid,
  ]);
} else {
  // Insert
  $ins = $pdo->prepare("
    INSERT INTO dbo.DOCENTE_PROYECTO_INTEGRADOR
      (ID_SOLICITUD, NOMBRE_PROYECTO, LISTA_ASIGNATURAS, NIVEL)
    VALUES
      (:sid, :nom, :lst, :niv)
  ");
  $ins->execute([
    ':sid' => $sid,
    ':nom' => $nombreProyecto,
    ':lst' => $listaAsig,
    ':niv' => $nivel,
  ]);
}

header('Location: /siged/public/index.php?action=jefe_ver&id='.$sid.'&cpi_saved=1');
exit;
