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

$sid = (int)($_POST['id'] ?? 0);
if ($sid <= 0) {
  http_response_code(400);
  exit('ID de solicitud inválido');
}

// Validar solicitud CST + depto
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
if (strtoupper((string)$S['TIPO_DOCUMENTO']) !== 'CST') {
  http_response_code(403);
  exit('La solicitud no es de tipo CST');
}

$depApr = (int)($S['ID_DEPARTAMENTO_APROBADOR'] ?? 0);
if ($depApr === 0 || $depApr !== $miDep) {
  http_response_code(403);
  exit('No tienes permisos para capturar datos de esta solicitud');
}

// Datos del formulario
$tipoExamen      = trim((string)($_POST['tipo_examen'] ?? ''));
$fechaExamen     = trim((string)($_POST['fecha_examen'] ?? ''));
$nomEst          = trim((string)($_POST['nombre_estudiante'] ?? ''));
$programa        = trim((string)($_POST['programa_educativo'] ?? ''));
$folioActa       = trim((string)($_POST['folio_acta'] ?? ''));
$rolPart         = trim((string)($_POST['rol_participacion'] ?? ''));

if ($tipoExamen === '' || $fechaExamen === '' || $nomEst === '' ||
    $programa === '' || $folioActa === '' || $rolPart === '') {
  header('Location: /siged/public/index.php?action=jefe_ver&id='.$sid.'&cst_err=campos');
  exit;
}

// Calcular ORDEN
$ordenSt = $pdo->prepare("
  SELECT ISNULL(MAX(ORDEN),0)+1
  FROM dbo.DOCENTE_SINODALIA_TITULACION
  WHERE ID_SOLICITUD=:sid
");
$ordenSt->execute([':sid' => $sid]);
$orden = (int)($ordenSt->fetchColumn() ?: 1);

// Insertar
$ins = $pdo->prepare("
  INSERT INTO dbo.DOCENTE_SINODALIA_TITULACION
    (ID_SOLICITUD, TIPO_EXAMEN, NOMBRE_ESTUDIANTE,
     PROGRAMA_EDUCATIVO, FOLIO_ACTA, FECHA_EXAMEN,
     ROL_PARTICIPACION, ORDEN)
  VALUES
    (:sid, :tip, :est, :prog, :fol, :fec, :rol, :ord)
");
$ins->execute([
  ':sid' => $sid,
  ':tip' => $tipoExamen,
  ':est' => $nomEst,
  ':prog'=> $programa,
  ':fol' => $folioActa,
  ':fec' => $fechaExamen,
  ':rol' => $rolPart,
  ':ord' => $orden,
]);

header('Location: /siged/public/index.php?action=jefe_ver&id='.$sid.'&cst_saved=1');
exit;
