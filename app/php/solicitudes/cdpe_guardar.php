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

$sid   = (int)($_POST['id'] ?? 0);
$idReg = (int)($_POST['id_dip_estrategico'] ?? 0);

if ($sid <= 0) {
  http_response_code(400);
  exit('ID de solicitud inválido');
}

// Validar solicitud CDPE + depto
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
if (strtoupper((string)$S['TIPO_DOCUMENTO']) !== 'CDPE') {
  http_response_code(403);
  exit('La solicitud no es de tipo CDPE');
}

$depApr = (int)($S['ID_DEPARTAMENTO_APROBADOR'] ?? 0);
if ($depApr === 0 || $depApr !== $miDep) {
  http_response_code(403);
  exit('No tienes permisos para capturar datos de esta solicitud');
}

// Datos del formulario
$nombreDiplomado = trim((string)($_POST['nombre_diplomado'] ?? ''));
$nombreProyecto  = trim((string)($_POST['nombre_proyecto'] ?? ''));

if ($nombreDiplomado === '' || $nombreProyecto === '') {
  header('Location: /siged/public/index.php?action=jefe_ver&id='.$sid.'&cdpe_err=campos');
  exit;
}

// Upsert
$exSt = $pdo->prepare("
  SELECT ID_DIPLOMADO_ESTRATEGICO
  FROM dbo.DOCENTE_DIPLOMADO_ESTRATEGICO
  WHERE ID_SOLICITUD = :sid
");
$exSt->execute([':sid' => $sid]);
$exId = (int)($exSt->fetchColumn() ?: 0);

if ($exId > 0) {
  $up = $pdo->prepare("
    UPDATE dbo.DOCENTE_DIPLOMADO_ESTRATEGICO
    SET NOMBRE_DIPLOMADO = :dip,
        NOMBRE_PROYECTO  = :proy
    WHERE ID_SOLICITUD   = :sid
  ");
  $up->execute([
    ':dip' => $nombreDiplomado,
    ':proy'=> $nombreProyecto,
    ':sid' => $sid,
  ]);
} else {
  $ins = $pdo->prepare("
    INSERT INTO dbo.DOCENTE_DIPLOMADO_ESTRATEGICO
      (ID_SOLICITUD, NOMBRE_DIPLOMADO, NOMBRE_PROYECTO)
    VALUES
      (:sid, :dip, :proy)
  ");
  $ins->execute([
    ':sid' => $sid,
    ':dip' => $nombreDiplomado,
    ':proy'=> $nombreProyecto,
  ]);
}

header('Location: /siged/public/index.php?action=jefe_ver&id='.$sid.'&cdpe_saved=1');
exit;
