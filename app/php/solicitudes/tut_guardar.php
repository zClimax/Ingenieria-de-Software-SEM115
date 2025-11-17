<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';
requireRole(['JEFE_DEPARTAMENTO']);

$pdo     = DB::conn();
$u       = Session::user();
$idUsr   = (int)($u['id'] ?? $u['ID_USUARIO'] ?? 0);
$depJefe = (int)($u['id_departamento'] ?? $u['ID_DEPARTAMENTO'] ?? 0);
if ($depJefe <= 0 && $idUsr > 0) {
  $q = $pdo->prepare("SELECT ID_DEPARTAMENTO FROM dbo.USUARIOS WHERE ID_USUARIO=:u");
  $q->execute([':u'=>$idUsr]);
  $depJefe = (int)($q->fetchColumn() ?: 0);
}

$id   = (int)($_POST['id'] ?? 0);
$tEJ  = max(0, (int)($_POST['tut_ej'] ?? 0));
$tAD  = max(0, (int)($_POST['tut_ad'] ?? 0));
$lug  = trim((string)($_POST['lugar'] ?? 'Culiacán, Sinaloa'));
$fch  = trim((string)($_POST['fecha'] ?? date('Y-m-d')));

if ($id<=0) die('ID inválido');

$qSol = $pdo->prepare("SELECT ID_DEPARTAMENTO_APROBADOR, TIPO_DOCUMENTO, ESTADO FROM dbo.SOLICITUD_DOCUMENTO WHERE ID_SOLICITUD=:id");
$qSol->execute([':id'=>$id]);
$S = $qSol->fetch(PDO::FETCH_ASSOC);
if (!$S || $S['TIPO_DOCUMENTO']!=='TUT') die('Solicitud inválida.');
if ((int)$S['ID_DEPARTAMENTO_APROBADOR'] !== $depJefe) die('No autorizado.');
if ($S['ESTADO']!=='ENVIADA') {
  header('Location: /siged/public/index.php?action=jefe_ver&id='.$id.'&msg=No%20editable%20en%20'.$S['ESTADO']); exit;
}

try { $fch = (new DateTime($fch))->format('Y-m-d'); } catch (\Throwable $e) { $fch = date('Y-m-d'); }

$ins = $pdo->prepare("
  INSERT INTO dbo.DOC_SE_TUTORADOS
  (ID_SOLICITUD, TUT_EJ_2024, TUT_AD_2024, LUGAR, FECHA_EMISION)
  VALUES (:id, :ej, :ad, :l, :f)
");
$ins->execute([':id'=>$id, ':ej'=>$tEJ, ':ad'=>$tAD, ':l'=>$lug, ':f'=>$fch]);

header('Location: /siged/public/index.php?action=jefe_ver&id='.$id.'&msg=Datos%20guardados');
exit;
