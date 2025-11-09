<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../utils/roles.php';
require_once __DIR__ . '/../config.php';
requireRole(['DOCENTE']);

$pdo=DB::conn(); $u=Session::user(); $idUsuario=(int)$u['id'];
$id=(int)($_POST['id']??0); $texto=trim($_POST['texto']??'');

try{
  if($id<=0 || $texto==='') throw new Exception('Datos inválidos');

  // validar pertenencia
  $chk=$pdo->prepare("SELECT T.ID_TICKET FROM dbo.TICKETS T
    JOIN dbo.DOCENTE D ON D.ID_USUARIO=:u
    WHERE T.ID_TICKET=:id AND T.ID_DOCENTE_GENERADOR=D.ID_DOCENTE");
  $chk->execute([':u'=>$idUsuario,':id'=>$id]); if(!$chk->fetch()) throw new Exception('No autorizado');

  // obtener ID_DOCENTE
  $d=$pdo->prepare("SELECT ID_DOCENTE FROM dbo.DOCENTE WHERE ID_USUARIO=:u");
  $d->execute([':u'=>$idUsuario]); $row=$d->fetch(); $idDoc=(int)$row['ID_DOCENTE'];

  $ins=$pdo->prepare("INSERT INTO dbo.TICKET_COMENTARIO (ID_TICKET, ID_USUARIO, ID_DOCENTE, TEXTO)
                      VALUES (:t, NULL, :doc, :txt)");
  $ins->execute([':t'=>$id, ':doc'=>$idDoc, ':txt'=>$texto]);

  // opcional: mover a EN_REVISION si estaba ABIERTO
  $pdo->prepare("UPDATE dbo.TICKETS SET ESTATUS=CASE WHEN ESTATUS='ABIERTO' THEN 'EN_REVISION' ELSE ESTATUS END WHERE ID_TICKET=:t")
      ->execute([':t'=>$id]);

  header('Location: /SIGED/public/index.php?action=tk_ver&id='.$id);
}catch(Throwable $e){
  http_response_code(500); echo "Error: ".$e->getMessage();
}
