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

// Validar solicitud CSEM + depto
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
if (strtoupper((string)$S['TIPO_DOCUMENTO']) !== 'CSEM') {
  http_response_code(403);
  exit('La solicitud no es de tipo CSEM');
}

$depApr = (int)($S['ID_DEPARTAMENTO_APROBADOR'] ?? 0);
if ($depApr === 0 || $depApr !== $miDep) {
  http_response_code(403);
  exit('No tienes permisos para capturar datos de esta solicitud');
}

// Datos del formulario
$periodo = trim((string)($_POST['periodo'] ?? ''));
$nivel   = trim((string)($_POST['nivel'] ?? ''));
$clave   = trim((string)($_POST['clave_materia'] ?? ''));
$nombre  = trim((string)($_POST['nombre_materia'] ?? ''));

$esc     = (int)($_POST['esc'] ?? 0);
$noesc   = (int)($_POST['noesc'] ?? 0);
$mix     = (int)($_POST['mix'] ?? 0);

if ($periodo === '' || $nivel === '' || $clave === '' || $nombre === '') {
  header('Location: /siged/public/index.php?action=jefe_ver&id='.$sid.'&csem_err=campos');
  exit;
}

// Calcular ORDEN
$ordenSt = $pdo->prepare("SELECT ISNULL(MAX(ORDEN),0)+1 FROM dbo.DOCENTE_CARGA_MODALIDAD WHERE ID_SOLICITUD=:sid");
$ordenSt->execute([':sid' => $sid]);
$orden = (int)($ordenSt->fetchColumn() ?: 1);

// Insertar
$ins = $pdo->prepare("
  INSERT INTO dbo.DOCENTE_CARGA_MODALIDAD
    (ID_SOLICITUD, PERIODO, NIVEL, CLAVE_MATERIA, NOMBRE_MATERIA,
     ALUMNOS_ESCOLARIZADA, ALUMNOS_NO_ESCOLARIZADA, ALUMNOS_MIXTA, ORDEN)
  VALUES
    (:sid, :per, :niv, :cla, :nom, :esc, :noesc, :mix, :ord)
");
$ins->execute([
  ':sid' => $sid,
  ':per' => $periodo,
  ':niv' => $nivel,
  ':cla' => $clave,
  ':nom' => $nombre,
  ':esc' => $esc,
  ':noesc'=> $noesc,
  ':mix' => $mix,
  ':ord' => $orden,
]);

header('Location: /siged/public/index.php?action=jefe_ver&id='.$sid.'&csem_saved=1');
exit;
