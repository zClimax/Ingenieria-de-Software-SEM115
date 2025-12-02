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
$idJurado = (int)($_POST['id_jurado'] ?? 0);

if ($sid <= 0) {
  http_response_code(400);
  exit('ID de solicitud inválido');
}

// Validar solicitud CJEA + depto
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
if (strtoupper((string)$S['TIPO_DOCUMENTO']) !== 'CJEA') {
  http_response_code(403);
  exit('La solicitud no es de tipo CJEA');
}

$depApr = (int)($S['ID_DEPARTAMENTO_APROBADOR'] ?? 0);
if ($depApr === 0 || $depApr !== $miDep) {
  http_response_code(403);
  exit('No tienes permisos para capturar datos de esta solicitud');
}

// Datos del formulario
$nombreEvento = trim((string)($_POST['nombre_evento'] ?? ''));
$fechaEvento  = trim((string)($_POST['fecha_evento'] ?? ''));
$lugarEvento  = trim((string)($_POST['lugar_evento'] ?? ''));

if ($nombreEvento === '' || $fechaEvento === '' || $lugarEvento === '') {
  header('Location: /siged/public/index.php?action=jefe_ver&id='.$sid.'&cjea_err=campos');
  exit;
}

// Upsert por ID_SOLICITUD
$exSt = $pdo->prepare("
  SELECT ID_JURADO_EVENTO
  FROM dbo.DOCENTE_JURADO_EVENTO
  WHERE ID_SOLICITUD = :sid
");
$exSt->execute([':sid' => $sid]);
$exId = (int)($exSt->fetchColumn() ?: 0);

if ($exId > 0) {
  $up = $pdo->prepare("
    UPDATE dbo.DOCENTE_JURADO_EVENTO
    SET NOMBRE_EVENTO = :nom,
        FECHA_EVENTO  = :fec,
        LUGAR_EVENTO  = :lug
    WHERE ID_SOLICITUD = :sid
  ");
  $up->execute([
    ':nom' => $nombreEvento,
    ':fec' => $fechaEvento,
    ':lug' => $lugarEvento,
    ':sid' => $sid,
  ]);
} else {
  $ins = $pdo->prepare("
    INSERT INTO dbo.DOCENTE_JURADO_EVENTO
      (ID_SOLICITUD, NOMBRE_EVENTO, FECHA_EVENTO, LUGAR_EVENTO)
    VALUES
      (:sid, :nom, :fec, :lug)
  ");
  $ins->execute([
    ':sid' => $sid,
    ':nom' => $nombreEvento,
    ':fec' => $fechaEvento,
    ':lug' => $lugarEvento,
  ]);
}

header('Location: /siged/public/index.php?action=jefe_ver&id='.$sid.'&cjea_saved=1');
exit;
