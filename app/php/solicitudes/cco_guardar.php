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
$idAsesoria= (int)($_POST['id_asesoria'] ?? 0);

if ($sid <= 0) {
  http_response_code(400);
  exit('ID de solicitud inválido');
}

// Validar solicitud CCO + depto
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
if (strtoupper((string)$S['TIPO_DOCUMENTO']) !== 'CCO') {
  http_response_code(403);
  exit('La solicitud no es de tipo CCO');
}

$depApr = (int)($S['ID_DEPARTAMENTO_APROBADOR'] ?? 0);
if ($depApr === 0 || $depApr !== $miDep) {
  http_response_code(403);
  exit('No tienes permisos para capturar datos de esta solicitud');
}

// Datos del formulario
$tipoEvento   = trim((string)($_POST['tipo_evento'] ?? ''));
$nombreEvento = trim((string)($_POST['nombre_evento'] ?? ''));
$lugarEvento  = trim((string)($_POST['lugar_evento'] ?? ''));
$fechaIni     = trim((string)($_POST['fecha_inicio'] ?? ''));
$fechaFin     = trim((string)($_POST['fecha_fin'] ?? ''));

if ($tipoEvento === '' || $nombreEvento === '' || $lugarEvento === '' ||
    $fechaIni === '' || $fechaFin === '') {
  header('Location: /siged/public/index.php?action=jefe_ver&id='.$sid.'&cco_err=campos');
  exit;
}

// Upsert por ID_SOLICITUD
$exSt = $pdo->prepare("
  SELECT ID_ASESORIA_CONCURSO
  FROM dbo.DOCENTE_ASESORIA_CONCURSO
  WHERE ID_SOLICITUD = :sid
");
$exSt->execute([':sid' => $sid]);
$exId = (int)($exSt->fetchColumn() ?: 0);

if ($exId > 0) {
  $up = $pdo->prepare("
    UPDATE dbo.DOCENTE_ASESORIA_CONCURSO
    SET TIPO_EVENTO   = :tip,
        NOMBRE_EVENTO = :nom,
        LUGAR_EVENTO  = :lug,
        FECHA_INICIO  = :fi,
        FECHA_FIN     = :ff
    WHERE ID_SOLICITUD = :sid
  ");
  $up->execute([
    ':tip' => $tipoEvento,
    ':nom' => $nombreEvento,
    ':lug' => $lugarEvento,
    ':fi'  => $fechaIni,
    ':ff'  => $fechaFin,
    ':sid' => $sid,
  ]);
} else {
  $ins = $pdo->prepare("
    INSERT INTO dbo.DOCENTE_ASESORIA_CONCURSO
      (ID_SOLICITUD, TIPO_EVENTO, NOMBRE_EVENTO, LUGAR_EVENTO, FECHA_INICIO, FECHA_FIN)
    VALUES
      (:sid, :tip, :nom, :lug, :fi, :ff)
  ");
  $ins->execute([
    ':sid' => $sid,
    ':tip' => $tipoEvento,
    ':nom' => $nombreEvento,
    ':lug' => $lugarEvento,
    ':fi'  => $fechaIni,
    ':ff'  => $fechaFin,
  ]);
}

header('Location: /siged/public/index.php?action=jefe_ver&id='.$sid.'&cco_saved=1');
exit;
