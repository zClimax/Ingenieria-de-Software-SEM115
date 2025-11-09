<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';
header('Content-Type: application/json; charset=UTF-8');

try{
  requireRole(['DOCENTE']); $pdo=DB::conn(); $u=Session::user();
  $idUsuario=(int)$u['id'];

  // 1) Depto por defecto: del docente
  $q=$pdo->prepare("SELECT U.ID_DEPARTAMENTO, D.ID_DOCENTE
                    FROM dbo.USUARIOS U JOIN dbo.DOCENTE D ON D.ID_USUARIO=U.ID_USUARIO
                    WHERE U.ID_USUARIO=:u");
  $q->execute([':u'=>$idUsuario]); $base=$q->fetch();
  if(!$base) { echo json_encode(['ok'=>false,'msg'=>'Docente no encontrado']); exit; }
  $deptDoc=(int)$base['ID_DEPARTAMENTO'];

  // 2) Si viene ?sol=ID_SOLICITUD, intentar usar depto aprobador de la solicitud
  $idSol = isset($_GET['sol']) ? (int)$_GET['sol'] : 0;
  $deptSug = $deptDoc;
  if ($idSol>0){
    $qs=$pdo->prepare("SELECT ID_DEPARTAMENTO_APROBADOR FROM dbo.SOLICITUD_DOCUMENTO WHERE ID_SOLICITUD=:s");
    $qs->execute([':s'=>$idSol]);
    if ($row=$qs->fetch()) $deptSug = (int)$row['ID_DEPARTAMENTO_APROBADOR'];
  }

  // 3) Catálogo de todos los jefes
  $sql="SELECT U.ID_USUARIO,
  COALESCE(U.NOMBRE_COMPLETO, U.NOMBRE_USUARIO) AS NOMBRE_MOSTRAR,
  U.CORREO,
  DP.ID_DEPARTAMENTO, DP.NOMBRE_DEPARTAMENTO
FROM dbo.USUARIOS U
LEFT JOIN dbo.DEPARTAMENTO DP ON DP.ID_DEPARTAMENTO = U.ID_DEPARTAMENTO
WHERE U.ID_ROL=2 AND U.ACTIVO=1
ORDER BY DP.NOMBRE_DEPARTAMENTO, NOMBRE_MOSTRAR";

  $all = $pdo->query($sql)->fetchAll();

  echo json_encode([
    'ok'=>true,
    'depto_sugerido'=>$deptSug,
    'items'=>$all
  ]);
}catch(Throwable $e){
  http_response_code(500); echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
