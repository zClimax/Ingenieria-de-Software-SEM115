<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';
header('Content-Type: application/json; charset=UTF-8');

try{
  requireRole(['DOCENTE']); $pdo=DB::conn(); $u=Session::user();
  $idUsuario=(int)$u['id'];

  // Obtener ID_DOCENTE
  $q=$pdo->prepare("SELECT ID_DOCENTE FROM dbo.DOCENTE WHERE ID_USUARIO=:u");
  $q->execute([':u'=>$idUsuario]); $d=$q->fetch();
  if(!$d){echo json_encode(['ok'=>false,'msg'=>'Docente no encontrado']);exit;}
  $idDoc=(int)$d['ID_DOCENTE'];

  $modo=$_GET['modo']??'abiertos';
  $estados = ($modo==='cerrados') ? "('CERRADO')" : "('ABIERTO','EN_REVISION')";

  $sql = "
  SELECT TOP 200 
         T.ID_TICKET, T.TITULO, T.DESCRIPCION, T.FECHA_CREACION, T.ESTATUS, T.PRIORIDAD,
         COALESCE(U.NOMBRE_COMPLETO, U.NOMBRE_USUARIO) AS JEFE_NOMBRE,
         U.CORREO AS JEFE_CORREO,
         DP.NOMBRE_DEPARTAMENTO AS JEFE_DEPTO
  FROM dbo.TICKETS T
  LEFT JOIN dbo.USUARIOS U      ON U.ID_USUARIO = T.ID_USUARIO_RESPONSABLE
  LEFT JOIN dbo.DEPARTAMENTO DP ON DP.ID_DEPARTAMENTO = U.ID_DEPARTAMENTO
  WHERE T.ID_DOCENTE_GENERADOR = :d
    AND T.ESTATUS IN $estados
  ORDER BY T.FECHA_CREACION DESC";

  $st=$pdo->prepare($sql); $st->execute([':d'=>$idDoc]);
  echo json_encode(['ok'=>true,'items'=>$st->fetchAll()]);
}catch(Throwable $e){
  http_response_code(500); echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
