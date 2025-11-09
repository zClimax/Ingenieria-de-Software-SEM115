<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../utils/roles.php';
require_once __DIR__ . '/../config.php';
requireRole(['JEFE_DEPARTAMENTO']);

$pdo=DB::conn(); $u=Session::user(); $idJefe=(int)$u['id'];
$id=(int)($_POST['id']??0);
$estatus=$_POST['estatus']??'EN_REVISION';
$prioridad=$_POST['prioridad']??'MEDIA';
$resol=trim($_POST['resolucion']??'');

try{
  if($id<=0) throw new Exception('ID inválido');

  // validar asignación
  $chk=$pdo->prepare("SELECT 1 FROM dbo.TICKETS WHERE ID_TICKET=:t AND ID_USUARIO_RESPONSABLE=:j");
  $chk->execute([':t'=>$id,':j'=>$idJefe]); if(!$chk->fetch()) throw new Exception('No autorizado');

  if($estatus==='CERRADO'){
    $sql="UPDATE dbo.TICKETS
          SET ESTATUS='CERRADO',
              PRIORIDAD=:p,
              RESOLUCION = NULLIF(:r,''),
              FECHA_CIERRE = SYSDATETIME()
          WHERE ID_TICKET=:t";
    $pdo->prepare($sql)->execute([':p'=>$prioridad, ':r'=>$resol, ':t'=>$id]);
  } else {
    $sql="UPDATE dbo.TICKETS
          SET ESTATUS=:e,
              PRIORIDAD=:p,
              RESOLUCION = CASE WHEN :e='CERRADO' THEN NULLIF(:r,'') ELSE RESOLUCION END
          WHERE ID_TICKET=:t";
    $pdo->prepare($sql)->execute([':e'=>$estatus, ':p'=>$prioridad, ':r'=>$resol, ':t'=>$id]);
  }

  header('Location: /SIGED/public/index.php?action=tkj_ver&id='.$id);
}catch(Throwable $e){
  http_response_code(500); echo "Error: ".$e->getMessage();
}
