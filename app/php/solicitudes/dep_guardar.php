<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';

requireRole(['JEFE_DEPARTAMENTO']);
Session::start();

$pdo  = DB::conn();
$user = Session::user();

$sid  = (int)($_POST['id'] ?? 0);
$asig = trim((string)($_POST['asignatura'] ?? ''));
$prog = trim((string)($_POST['programa'] ?? ''));
$ofi  = trim((string)($_POST['oficio'] ?? ''));
$lug  = trim((string)($_POST['lugar'] ?? ''));
$fOfi = trim((string)($_POST['f_oficio'] ?? ''));

if ($sid<=0 || $asig==='' || $prog==='') { http_response_code(400); exit('Datos incompletos'); }

// Seguridad: el jefe debe ser el aprobador de la solicitud
$S = $pdo->prepare("SELECT ID_DEPARTAMENTO_APROBADOR FROM dbo.SOLICITUD_DOCUMENTO WHERE ID_SOLICITUD=:id");
$S->execute([':id'=>$sid]);
$depApr = (int)($S->fetchColumn() ?: 0);

$depJefe = (int)($user['id_departamento'] ?? 0);
if ($depJefe<=0) {
  $q = $pdo->prepare("SELECT ID_DEPARTAMENTO FROM dbo.USUARIOS WHERE ID_USUARIO=:u");
  $q->execute([':u'=>(int)$user['id']]);
  $depJefe = (int)($q->fetchColumn() ?: 0);
}
if ($depApr !== $depJefe) { http_response_code(403); exit('No tienes permisos sobre esta solicitud'); }

// UPSERT
$sql = "
MERGE dbo.DOC_DEP_RECURSO AS T
USING (VALUES(:id,:asig,:prog,:ofi,:lug,:f)) AS S(ID_SOLICITUD,ASIGNATURA,PROGRAMA_EDUCATIVO,OFICIO_NO,LUGAR,FECHA_OFICIO)
ON (T.ID_SOLICITUD=S.ID_SOLICITUD)
WHEN MATCHED THEN UPDATE SET
  ASIGNATURA=S.ASIGNATURA, PROGRAMA_EDUCATIVO=S.PROGRAMA_EDUCATIVO,
  OFICIO_NO=S.OFICIO_NO, LUGAR=S.LUGAR, FECHA_OFICIO=S.FECHA_OFICIO
WHEN NOT MATCHED THEN INSERT (ID_SOLICITUD,ASIGNATURA,PROGRAMA_EDUCATIVO,OFICIO_NO,LUGAR,FECHA_OFICIO)
VALUES (S.ID_SOLICITUD,S.ASIGNATURA,S.PROGRAMA_EDUCATIVO,S.OFICIO_NO,S.LUGAR,S.FECHA_OFICIO);";
$st = $pdo->prepare($sql);
$st->execute([
  ':id'=>$sid, ':asig'=>$asig, ':prog'=>$prog,
  ':ofi'=>($ofi===''? null : $ofi),
  ':lug'=>($lug===''? null : $lug),
  ':f'  =>($fOfi===''? null : $fOfi),
]);

header('Location: /siged/public/index.php?action=jefe_ver&id='.$sid.'&dep_ok=1');
exit;
