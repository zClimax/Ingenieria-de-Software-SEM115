<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';

requireRole(['DOCENTE']);
Session::start();

$pdo = DB::conn();
$user = Session::user();
$idUsuario = (int)($user['id'] ?? 0);

$tipo = strtoupper(trim((string)($_POST['tipo'] ?? '')));
if ($tipo === '') { http_response_code(400); exit('Tipo de documento requerido'); }

// 1) Docente + Departamento del docente
$q = $pdo->prepare("
  SELECT D.ID_DOCENTE, U.ID_DEPARTAMENTO
  FROM dbo.DOCENTE D
  JOIN dbo.USUARIOS U ON U.ID_USUARIO = D.ID_USUARIO
  WHERE D.ID_USUARIO = :u
");
$q->execute([':u' => $idUsuario]);
$row = $q->fetch(PDO::FETCH_ASSOC);
if (!$row) { http_response_code(403); exit('Docente no encontrado'); }

$idDoc  = (int)$row['ID_DOCENTE'];
$depDoc = (int)$row['ID_DEPARTAMENTO'];

// 2) Convocatoria activa
$conv = (int)$pdo->query("SELECT TOP 1 ID_CONVOCATORIA FROM dbo.CONVOCATORIA WHERE ACTIVO=1 ORDER BY ID_CONVOCATORIA DESC")->fetchColumn();
if ($conv <= 0) { http_response_code(400); exit('No hay convocatoria activa'); }

// 3) Plantilla del tipo
$tpl = $pdo->prepare("SELECT TOP 1 ID_DEPARTAMENTO_APROBADOR FROM dbo.PLANTILLA_DOC WHERE TIPO_DOCUMENTO=:t AND ACTIVO=1 ORDER BY ID_PLANTILLA DESC");
$tpl->execute([':t'=>$tipo]);
$tplRow = $tpl->fetch(PDO::FETCH_ASSOC);
if (!$tplRow) { http_response_code(400); exit('No existe plantilla activa para el tipo'); }

$depApr = (int)($tplRow['ID_DEPARTAMENTO_APROBADOR'] ?? 0);

// 4) Si la plantilla no define aprobador, usar SIEMPRE el depto del docente
if ($depApr === 0) { $depApr = $depDoc; }

// 5) Regla de negocio: RED solo puede ser aprobado por el mismo departamento del docente
if ($tipo === 'RED' && $depApr !== $depDoc) {
  // Enforzamos al del docente (o puedes lanzar 403 si prefieres)
  $depApr = $depDoc;
}

if ($tipo === 'ESTR') {
  // depto del usuario que abre la solicitud
  $depAprob = (int)($docRow['ID_DEPARTAMENTO'] ?? 0);
}

$depAprob = null;
if ($tipo === 'TUT') {
  $depAprob = 14; // Servicios Escolares
}


// 6) Crear solicitud en BORRADOR con aprobador fijo
$sql = $pdo->prepare("
  INSERT INTO dbo.SOLICITUD_DOCUMENTO
    (ID_DOCENTE, ID_DEPARTAMENTO, TIPO_DOCUMENTO, ESTADO, FECHA_CREACION,
     ID_DEPARTAMENTO_APROBADOR, ID_CONVOCATORIA)
  VALUES
    (:doc, :depDoc, :tipo, 'BORRADOR', SYSDATETIME(), :depApr, :conv)
");
$sql->execute([
  ':doc'    => $idDoc,
  ':depDoc' => $depDoc,
  ':tipo'   => $tipo,
  ':depApr' => $depApr,
  ':conv'   => $conv,
]);

// Redirige al listado o a editar, según tu UX actual
header('Location: /siged/public/index.php?action=sol_mis');
exit;
