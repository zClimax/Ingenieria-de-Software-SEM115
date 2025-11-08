<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
Session::start();

$user = Session::user();
if ($user['rol'] !== 'DOCENTE') { http_response_code(403); exit('Solo docentes'); }

$pdo = DB::conn();

$S = Config::MAP['SOLICITUD'];
$D = Config::MAP['DOCENTE'];
$U = Config::MAP['USUARIOS'];
$R = Config::MAP['RUTEADOR'];

$ts    = $S['TABLE'];
$sId   = $S['ID'];
$sDoc  = $S['DOC'];
$sDep  = $S['DEP'];          // (opcional) depto del docente
$sTipo = $S['TIPO'];
$sDepA = $S['DEP_APROB'];    // ✅ depto aprobador (nuevo)

$tipo = trim($_POST['tipo'] ?? '');
if ($tipo === '') { die('Tipo requerido'); }

/* 1) ID_DOCENTE (vía usuario en sesión) */
$sqlInfo = "
  SELECT D.{$D['ID']} AS id_doc, U.{$U['DEP']} AS id_dep_docente
  FROM   {$D['TABLE']} D
  JOIN   {$U['TABLE']} U ON U.{$U['ID']} = D.{$D['ID_USR']}
  WHERE  D.{$D['ID_USR']} = :uid
";
$stmt = $pdo->prepare($sqlInfo);
$stmt->execute([':uid' => $user['id']]);
$info = $stmt->fetch();
if (!$info) { die('No se encontró tu vínculo de docente/usuario.'); }
$idDoc   = (int)$info['id_doc'];
$idDepDoc= isset($info['id_dep_docente']) ? (int)$info['id_dep_docente'] : null;

/* 2) Buscar depto aprobador para el TIPO */
$stmt = $pdo->prepare("SELECT {$R['DEP']} AS dep_aprob FROM {$R['TABLE']} WHERE {$R['TIPO']} = :t");
$stmt->execute([':t'=>$tipo]);
$rowR = $stmt->fetch();
$depAprob = $rowR ? (int)$rowR['dep_aprob'] : null;

/* 3) Insertar con OUTPUT INSERTED */
$sqlIns = "INSERT INTO $ts ($sDoc,$sDep,$sTipo,$sDepA)
           OUTPUT INSERTED.$sId
           VALUES (:doc,:dep_doc,:tipo,:dep_aprob)";
$stmt = $pdo->prepare($sqlIns);
$stmt->execute([
  ':doc'       => $idDoc,
  ':dep_doc'   => $idDepDoc,
  ':tipo'      => $tipo,
  ':dep_aprob' => $depAprob
]);
$newId = (int)$stmt->fetchColumn();
if ($newId <= 0) { die('No se pudo obtener el ID de la solicitud.'); }

header('Location: /siged/public/index.php?action=sol_editar&id='.$newId);
exit;
