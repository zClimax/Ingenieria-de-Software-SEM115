<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';
requireRole(['JEFE_DEPARTAMENTO']);

$pdo = DB::conn();
$u        = Session::user();
$idUsuario = (int)($u['id'] ?? $u['ID_USUARIO'] ?? 0);
$depJefe   = (int)($u['id_departamento'] ?? $u['ID_DEPARTAMENTO'] ?? 0);
if ($depJefe <= 0 && $idUsuario > 0) {
  $q = $pdo->prepare("SELECT ID_DEPARTAMENTO FROM dbo.USUARIOS WHERE ID_USUARIO=:u");
  $q->execute([':u'=>$idUsuario]);
  $depJefe = (int)($q->fetchColumn() ?: 0);
}

$id   = (int)($_POST['id'] ?? 0);
if ($id <= 0) die('ID inválido');

$S = $pdo->prepare("SELECT ID_DEPARTAMENTO_APROBADOR, TIPO_DOCUMENTO FROM dbo.SOLICITUD_DOCUMENTO WHERE ID_SOLICITUD=:id");
$S->execute([':id'=>$id]);
$sol = $S->fetch(PDO::FETCH_ASSOC);
if (!$sol || $sol['TIPO_DOCUMENTO'] !== 'ESTR') die('Solicitud no encontrada.');
if ((int)$sol['ID_DEPARTAMENTO_APROBADOR'] !== $depJefe) die('No autorizado');

$asig = trim($_POST['asignatura'] ?? '');
$prog = trim($_POST['programa'] ?? '');
$estr = trim($_POST['estrategia'] ?? '');
$lug  = trim($_POST['lugar'] ?? '');
$fch  = trim($_POST['fecha'] ?? '');

if ($asig==='' || $prog==='' || $estr==='') die('Datos incompletos.');

$pdo->prepare("INSERT INTO dbo.DOC_DEP_ESTRAT
  (ID_SOLICITUD, ASIGNATURA, ESTRATEGIA, PROGRAMA_EDUCATIVO, LUGAR, FECHA_EMISION)
  VALUES (:id,:a,:e,:p,:l,TRY_CONVERT(date,:f))")
  ->execute([':id'=>$id, ':a'=>$asig, ':e'=>$estr, ':p'=>$prog, ':l'=>$lug, ':f'=>$fch]);

header('Location: /siged/public/index.php?action=jefe_ver&id='.$id);
