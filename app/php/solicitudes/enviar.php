<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';
requireRole(['DOCENTE']);

$pdo = DB::conn();
$S = Config::MAP['SOLICITUD'];
$R = Config::MAP['RUTEADOR'];

$id = (int)($_POST['id'] ?? 0);
if ($id<=0) { die('ID inválido'); }

/* 1) Asegurar que la solicitud tenga el depto aprobador acorde a su TIPO */
$ts=$S['TABLE']; $sTipo=$S['TIPO']; $sDepA=$S['DEP_APROB']; $sId=$S['ID'];
$tipo = $pdo->query("SELECT $sTipo AS t FROM $ts WHERE $sId=$id")->fetch()['t'] ?? null;

if ($tipo) {
  $stmt = $pdo->prepare("SELECT {$R['DEP']} AS dep_aprob FROM {$R['TABLE']} WHERE {$R['TIPO']} = :t");
  $stmt->execute([':t'=>$tipo]);
  $rowR = $stmt->fetch();
  if ($rowR && $rowR['dep_aprob'] !== null) {
    $pdo->prepare("UPDATE $ts SET $sDepA = :d WHERE $sId = :id AND $sDepA IS NULL")
        ->execute([':d'=>(int)$rowR['dep_aprob'], ':id'=>$id]);
  }
}

/* 2) Cambiar a ENVIADA */
$sqlEnv = "UPDATE {$S['TABLE']} SET {$S['ESTADO']}='ENVIADA', {$S['F_ENV']}=SYSDATETIME()
           WHERE {$S['ID']}=:id AND {$S['ESTADO']}='BORRADOR'";
$pdo->prepare($sqlEnv)->execute([':id'=>$id]);

header('Location: /siged/public/index.php?action=sol_mis');
