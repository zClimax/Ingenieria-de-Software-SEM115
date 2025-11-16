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

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) { http_response_code(400); exit('ID inválido'); }

// 1) Validar que la solicitud es del docente actual y está en BORRADOR
$sol = $pdo->prepare("
  SELECT S.ID_SOLICITUD, S.TIPO_DOCUMENTO, S.ESTADO, S.ID_DEPARTAMENTO_APROBADOR,
         D.ID_DOCENTE, U.ID_DEPARTAMENTO
  FROM dbo.SOLICITUD_DOCUMENTO S
  JOIN dbo.DOCENTE D   ON D.ID_DOCENTE = S.ID_DOCENTE
  JOIN dbo.USUARIOS U  ON U.ID_USUARIO = D.ID_USUARIO
  WHERE S.ID_SOLICITUD = :id AND D.ID_USUARIO = :u
");
$sol->execute([':id'=>$id, ':u'=>$idUsuario]);
$row = $sol->fetch(PDO::FETCH_ASSOC);
if (!$row) { http_response_code(404); exit('Solicitud no encontrada'); }
if ($row['ESTADO'] !== 'BORRADOR') { http_response_code(400); exit('La solicitud no está en BORRADOR'); }

$tipo    = strtoupper((string)$row['TIPO_DOCUMENTO']);
$depDoc  = (int)$row['ID_DEPARTAMENTO'];
$depApr  = (int)($row['ID_DEPARTAMENTO_APROBADOR'] ?? 0);

// 2) Si el aprobador está vacío, resolverlo ahora
if ($depApr === 0) {
  $tpl = $pdo->prepare("SELECT TOP 1 ID_DEPARTAMENTO_APROBADOR FROM dbo.PLANTILLA_DOC WHERE TIPO_DOCUMENTO=:t AND ACTIVO=1 ORDER BY ID_PLANTILLA DESC");
  $tpl->execute([':t'=>$tipo]);
  $tplRow = $tpl->fetch(PDO::FETCH_ASSOC);
  $depApr = (int)($tplRow['ID_DEPARTAMENTO_APROBADOR'] ?? 0);

  if ($depApr === 0) { $depApr = $depDoc; }
  if ($tipo === 'RED' && $depApr !== $depDoc) { $depApr = $depDoc; }

  $u = $pdo->prepare("UPDATE dbo.SOLICITUD_DOCUMENTO SET ID_DEPARTAMENTO_APROBADOR=:apr WHERE ID_SOLICITUD=:id");
  $u->execute([':apr'=>$depApr, ':id'=>$id]);
}

// 3) Enviar
$upd = $pdo->prepare("UPDATE dbo.SOLICITUD_DOCUMENTO SET ESTADO='ENVIADA', FECHA_ENVIO=SYSDATETIME() WHERE ID_SOLICITUD=:id");
$upd->execute([':id'=>$id]);

header('Location: /siged/public/index.php?action=sol_mis');
exit;
