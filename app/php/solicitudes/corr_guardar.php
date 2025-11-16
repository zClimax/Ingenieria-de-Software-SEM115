<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';

requireRole(['DOCENTE']);
Session::start();

$pdo = DB::conn();
$u   = Session::user();

// 1) Entrada básica
$idSol  = (int)($_POST['id'] ?? 0);
$motivo = trim((string)($_POST['motivo'] ?? ''));

if ($idSol <= 0 || $motivo === '') {
  http_response_code(400);
  exit('Datos inválidos');
}

$idUsuario = (int)($u['id'] ?? 0);
if ($idUsuario <= 0) {
  http_response_code(403);
  exit('Sesión inválida');
}

// 2) Resolver el ID_DOCENTE del usuario actual (sin depender de la sesión)
$qDoc = $pdo->prepare("
  SELECT D.ID_DOCENTE
  FROM dbo.DOCENTE D
  JOIN dbo.USUARIOS U ON U.ID_USUARIO = D.ID_USUARIO
  WHERE U.ID_USUARIO = :idu
");
$qDoc->execute([':idu' => $idUsuario]);
$idDocente = (int)($qDoc->fetchColumn() ?: 0);
if ($idDocente <= 0) {
  http_response_code(403);
  exit('Docente no encontrado');
}

// 3) Traer la solicitud y validar propiedad/estado
$qSol = $pdo->prepare("
  SELECT ID_SOLICITUD, ID_DOCENTE, TIPO_DOCUMENTO, ESTADO, ID_DEPARTAMENTO_APROBADOR
  FROM dbo.SOLICITUD_DOCUMENTO
  WHERE ID_SOLICITUD = :id
");
$qSol->execute([':id' => $idSol]);
$sol = $qSol->fetch(PDO::FETCH_ASSOC);
if (!$sol) {
  http_response_code(404);
  exit('Solicitud no encontrada');
}
if ((int)$sol['ID_DOCENTE'] !== $idDocente) {
  http_response_code(403);
  exit('No autorizado');
}
if (($sol['ESTADO'] ?? '') !== 'APROBADA') {
  http_response_code(409);
  exit('Sólo se puede solicitar corrección para documentos APROBADOS.');
}

// 4) Evitar duplicados (ya existe corrección abierta o en edición)
$dup = $pdo->prepare("
  SELECT 1
  FROM dbo.DOC_CORRECCION
  WHERE ID_SOLICITUD = :id
    AND ESTATUS IN ('ABIERTA','EN_EDICION')
");
$dup->execute([':id' => $idSol]);
if ($dup->fetchColumn()) {
  http_response_code(409);
  exit('Ya existe una corrección abierta para esta solicitud.');
}

// 5) Determinar depto aprobador (de la solicitud o, si falta, de la plantilla)
$depDestino = (int)($sol['ID_DEPARTAMENTO_APROBADOR'] ?? 0);
if ($depDestino <= 0) {
  $qTpl = $pdo->prepare("
    SELECT TOP 1 ID_DEPARTAMENTO_APROBADOR
    FROM dbo.PLANTILLA_DOC
    WHERE TIPO_DOCUMENTO = :t AND ACTIVO = 1
    ORDER BY ID_DEPARTAMENTO_APROBADOR DESC
  ");
  $qTpl->execute([':t' => (string)$sol['TIPO_DOCUMENTO']]);
  $depDestino = (int)($qTpl->fetchColumn() ?: 0);
  if ($depDestino <= 0) {
    http_response_code(409);
    exit('No se pudo determinar el departamento aprobador.');
  }
}

// 6) Insertar solicitud de corrección
$ins = $pdo->prepare("
  INSERT INTO dbo.DOC_CORRECCION
    (ID_SOLICITUD, ID_DOCENTE, TIPO_DOCUMENTO, ID_DEP_DESTINO, MOTIVO, ESTATUS, CREATED_BY)
  VALUES
    (:id, :doc, :tipo, :dep, :motivo, 'ABIERTA', :usr)
");

$ins->execute([
  ':id'     => $idSol,
  ':doc'    => $idDocente,
  ':tipo'   => (string)$sol['TIPO_DOCUMENTO'],
  ':dep'    => $depDestino,
  ':motivo' => $motivo,
  ':usr'    => $idUsuario,
]);

// 7) Back to Mis Solicitudes
header('Location: /siged/public/index.php?action=sol_mis&corr=ok');
exit;
