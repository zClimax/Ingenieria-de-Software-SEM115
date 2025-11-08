<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';
requireRole(['JEFE_DEPARTAMENTO']);
$pdo = DB::conn();

$S=Config::MAP['SOLICITUD'];
$id = (int)($_POST['id'] ?? 0);
$decision = ($_POST['decision'] ?? '');
$coment = trim($_POST['comentario'] ?? '');

if ($id<=0 || !in_array($decision, ['APROBADA','RECHAZADA'], true)) { die('Datos inválidos'); }

$sql = "UPDATE ".$S['TABLE']." SET ".$S['ESTADO']."=:dec, ".$S['F_DEC']."=SYSDATETIME(), ".$S['COM_J']."=:c WHERE ".$S['ID']."=:id AND ".$S['ESTADO']."='ENVIADA'";
$stmt = $pdo->prepare($sql);
$stmt->execute([':dec'=>$decision, ':c'=>$coment, ':id'=>$id]);

header('Location: /siged/public/index.php?action=jefe_ver&id='.$id);
