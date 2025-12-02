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
$idAud     = (int)($_POST['id_auditoria'] ?? 0);

if ($sid <= 0) {
  http_response_code(400);
  exit('ID de solicitud inválido');
}

// Validar solicitud PASG + depto
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
if (strtoupper((string)$S['TIPO_DOCUMENTO']) !== 'PASG') {
  http_response_code(403);
  exit('La solicitud no es de tipo PASG');
}

$depApr = (int)($S['ID_DEPARTAMENTO_APROBADOR'] ?? 0);
if ($depApr === 0 || $depApr !== $miDep) {
  http_response_code(403);
  exit('No tienes permisos para capturar datos de esta solicitud');
}

// Datos del formulario
$tipoAud    = trim((string)($_POST['tipo_auditoria'] ?? ''));
$tipoSist   = trim((string)($_POST['tipo_sistema'] ?? ''));
$fechaIni   = trim((string)($_POST['fecha_inicio'] ?? ''));
$fechaFin   = trim((string)($_POST['fecha_fin'] ?? ''));
$lugar      = trim((string)($_POST['lugar'] ?? ''));

if ($tipoAud === '' || $tipoSist === '' || $fechaIni === '' || $fechaFin === '' || $lugar === '') {
  header('Location: /siged/public/index.php?action=jefe_ver&id='.$sid.'&pasg_err=campos');
  exit;
}

// Upsert por ID_SOLICITUD
$exSt = $pdo->prepare("
  SELECT ID_AUDITORIA_SG
  FROM dbo.DOCENTE_AUDITORIA_SG
  WHERE ID_SOLICITUD = :sid
");
$exSt->execute([':sid' => $sid]);
$exId = (int)($exSt->fetchColumn() ?: 0);

if ($exId > 0) {
  $up = $pdo->prepare("
    UPDATE dbo.DOCENTE_AUDITORIA_SG
    SET TIPO_AUDITORIA = :ta,
        TIPO_SISTEMA   = :ts,
        FECHA_INICIO   = :fi,
        FECHA_FIN      = :ff,
        LUGAR          = :lug
    WHERE ID_SOLICITUD = :sid
  ");
  $up->execute([
    ':ta'  => $tipoAud,
    ':ts'  => $tipoSist,
    ':fi'  => $fechaIni,
    ':ff'  => $fechaFin,
    ':lug' => $lugar,
    ':sid' => $sid,
  ]);
} else {
  $ins = $pdo->prepare("
    INSERT INTO dbo.DOCENTE_AUDITORIA_SG
      (ID_SOLICITUD, TIPO_AUDITORIA, TIPO_SISTEMA, FECHA_INICIO, FECHA_FIN, LUGAR)
    VALUES
      (:sid, :ta, :ts, :fi, :ff, :lug)
  ");
  $ins->execute([
    ':sid' => $sid,
    ':ta'  => $tipoAud,
    ':ts'  => $tipoSist,
    ':fi'  => $fechaIni,
    ':ff'  => $fechaFin,
    ':lug' => $lugar,
  ]);
}

header('Location: /siged/public/index.php?action=jefe_ver&id='.$sid.'&pasg_saved=1');
exit;
