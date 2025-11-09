<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';
header('Content-Type: application/json; charset=UTF-8');

try{
  requireRole(['JEFE_DEPARTAMENTO']); $pdo=DB::conn(); $u=Session::user();
  $idJefe=(int)$u['id'];

  $modo = $_GET['modo'] ?? 'abiertos';
  $in = "('ABIERTO')";
  if ($modo==='revision') $in="('EN_REVISION')";
  if ($modo==='cerrados') $in="('CERRADO')";

  $sql = "
    SELECT TOP 200 T.ID_TICKET, T.TITULO, T.FECHA_CREACION, T.ESTATUS, T.PRIORIDAD,
           CONCAT(D.NOMBRE_DOCENTE,' ',D.APELLIDO_PATERNO_DOCENTE,' ',D.APELLIDO_MATERNO_DOCENTE) AS DOCENTE
    FROM dbo.TICKETS T
    JOIN dbo.DOCENTE D ON D.ID_DOCENTE = T.ID_DOCENTE_GENERADOR
    WHERE T.ID_USUARIO_RESPONSABLE = :j
      AND T.ESTATUS IN $in
    ORDER BY T.FECHA_CREACION DESC, T.ID_TICKET DESC";
  $st=$pdo->prepare($sql); $st->execute([':j'=>$idJefe]);
  echo json_encode(['ok'=>true,'items'=>$st->fetchAll()]);
}catch(Throwable $e){
  http_response_code(500); echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
