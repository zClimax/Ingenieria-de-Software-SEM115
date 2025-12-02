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

// Validar solicitud CSEP + depto
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
if (strtoupper((string)$S['TIPO_DOCUMENTO']) !== 'CSEP') {
  http_response_code(403);
  exit('La solicitud no es de tipo CSEP');
}

$depApr = (int)($S['ID_DEPARTAMENTO_APROBADOR'] ?? 0);
if ($depApr === 0 || $depApr !== $miDep) {
  http_response_code(403);
  exit('No tienes permisos para capturar datos de esta solicitud');
}

// Datos del formulario
$periodo = trim((string)($_POST['periodo'] ?? ''));
$clave   = trim((string)($_POST['clave_materia'] ?? ''));
$nombre  = trim((string)($_POST['nombre_materia'] ?? ''));
$alum    = (int)($_POST['alumnos'] ?? 0);

// Nivel siempre Posgrado (no confiamos en el POST)
$nivel = 'Posgrado';

if ($periodo === '' || $clave === '' || $nombre === '') {
  header('Location: /siged/public/index.php?action=jefe_ver&id='.$sid.'&csep_err=campos');
  exit;
}

// Calcular ORDEN
$ordenSt = $pdo->prepare("SELECT ISNULL(MAX(ORDEN),0)+1 FROM dbo.DOCENTE_CARGA WHERE ID_SOLICITUD=:sid");
$ordenSt->execute([':sid' => $sid]);
$orden = (int)($ordenSt->fetchColumn() ?: 1);

// Insertar
$ins = $pdo->prepare("
  INSERT INTO dbo.DOCENTE_CARGA
    (ID_SOLICITUD, PERIODO, NIVEL, CLAVE_MATERIA, NOMBRE_MATERIA, ALUMNOS_ATENDIDOS, ORDEN)
  VALUES
    (:sid, :per, :niv, :cla, :nom, :alu, :ord)
");
$ins->execute([
  ':sid' => $sid,
  ':per' => $periodo,
  ':niv' => $nivel,
  ':cla' => $clave,
  ':nom' => $nombre,
  ':alu' => $alum,
  ':ord' => $orden,
]);

header('Location: /siged/public/index.php?action=jefe_ver&id='.$sid.'&csep_saved=1');
exit;
