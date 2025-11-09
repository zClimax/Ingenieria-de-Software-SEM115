<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../utils/roles.php';
require_once __DIR__ . '/../config.php';
requireRole(['JEFE_DEPARTAMENTO']);

$pdo=DB::conn(); $u=Session::user(); $idJefe=(int)$u['id'];
$id=(int)($_POST['id']??0); $texto=trim($_POST['texto']??'');

try{
  if($id<=0 || $texto==='') throw new Exception('Datos inválidos');

  // validar asignación
  $chk=$pdo->prepare("SELECT 1 FROM dbo.TICKETS WHERE ID_TICKET=:t AND ID_USUARIO_RESPONSABLE=:j");
  $chk->execute([':t'=>$id,':j'=>$idJefe]); if(!$chk->fetch()) throw new Exception('No autorizado');

  $ins=$pdo->prepare("INSERT INTO dbo.TICKET_COMENTARIO (ID_TICKET, ID_USUARIO, ID_DOCENTE, TEXTO)
                      VALUES (:t, :u, NULL, :txt)");
  $ins->execute([':t'=>$id, ':u'=>$idJefe, ':txt'=>$texto]);

  // Si estaba ABIERTO, pásalo a EN_REVISION y marca FECHA_ATENCION
  $pdo->prepare("UPDATE dbo.TICKETS
                 SET ESTATUS = CASE WHEN ESTATUS='ABIERTO' THEN 'EN_REVISION' ELSE ESTATUS END,
                     FECHA_ATENCION = COALESCE(FECHA_ATENCION, SYSDATETIME())
                 WHERE ID_TICKET=:t")->execute([':t'=>$id]);

  header('Location: /SIGED/public/index.php?action=tkj_ver&id='.$id);
}catch(Throwable $e){
  http_response_code(500); echo "Error: ".$e->getMessage();
}
