<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';

requireRole(['DOCENTE']); $pdo=DB::conn(); $u=Session::user();
$idUsuario=(int)$u['id'];

try{
  // Docente & depto
  $q=$pdo->prepare("SELECT D.ID_DOCENTE, U.ID_DEPARTAMENTO
                    FROM dbo.DOCENTE D JOIN dbo.USUARIOS U ON U.ID_USUARIO=D.ID_USUARIO
                    WHERE D.ID_USUARIO=:u");
  $q->execute([':u'=>$idUsuario]); $row=$q->fetch();
  if(!$row) throw new Exception('Docente no encontrado');
  $idDoc=(int)$row['ID_DOCENTE']; $idDept=(int)$row['ID_DEPARTAMENTO'];

  // Jefe responsable (primer usuario con rol 2 en el depto)
  $j=$pdo->prepare("SELECT TOP 1 ID_USUARIO FROM dbo.USUARIOS WHERE ID_ROL=2 AND ID_DEPARTAMENTO=:d AND ACTIVO=1 ORDER BY ID_USUARIO");
  $j->execute([':d'=>$idDept]); $resp=$j->fetch();
  
$idResp = null;
if (!empty($_POST['responsable'])) {
  $cand = (int)$_POST['responsable'];
  // Debe ser un usuario activo con rol JEFE_DEPARTAMENTO
  $v = $pdo->prepare("SELECT 1 FROM dbo.USUARIOS WHERE ID_USUARIO=:id AND ID_ROL=2 AND ACTIVO=1");
  $v->execute([':id'=>$cand]);
  if ($v->fetch()) $idResp = $cand;
}

if ($idResp === null) {
  // Fallback: jefe del depto del docente (lógica anterior)
  $j=$pdo->prepare("SELECT TOP 1 ID_USUARIO FROM dbo.USUARIOS WHERE ID_ROL=2 AND ID_DEPARTAMENTO=:d AND ACTIVO=1 ORDER BY ID_USUARIO");
  $j->execute([':d'=>$idDept]); $resp=$j->fetch();
  $idResp = $resp ? (int)$resp['ID_USUARIO'] : null;
}


  $sql="INSERT INTO dbo.TICKETS (ID_USUARIO, ID_DOCENTE_GENERADOR, ID_USUARIO_RESPONSABLE,
                                 TIPO_TICKET, TITULO, DESCRIPCION, ESTATUS, PRIORIDAD)
        VALUES (:u,:d,:r,:tipo,:tit,:desc,'ABIERTO',:prio)";
  $st=$pdo->prepare($sql);
  $st->execute([
    ':u'=>$idUsuario, ':d'=>$idDoc, ':r'=>$idResp,
    ':tipo'=>$_POST['tipo']??'OTRO',
    ':tit'=>trim($_POST['titulo']??''),
    ':desc'=>trim($_POST['descripcion']??''),
    ':prio'=>$_POST['prioridad']??'MEDIA'
  ]);

  header('Location: /SIGED/public/index.php?action=tk_list');
}catch(Throwable $e){
  http_response_code(500);
  echo "Error al crear ticket: ".$e->getMessage();
}
