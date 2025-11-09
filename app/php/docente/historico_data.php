<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';

header('Content-Type: application/json; charset=UTF-8');

try {
  requireRole(['DOCENTE']);
  $pdo = DB::conn();
  $u = Session::user();
  $idUsuario = (int)$u['id'];

  // Docente
  $sqlDoc = "SELECT ID_DOCENTE FROM dbo.DOCENTE WHERE ID_USUARIO=:u";
  $st = $pdo->prepare($sqlDoc); $st->execute([':u'=>$idUsuario]);
  $doc = $st->fetch();
  if(!$doc){ echo json_encode(['ok'=>false,'msg'=>'Docente no encontrado']); exit; }
  $idDoc = (int)$doc['ID_DOCENTE'];

  // Modo: resumen (todas) o detalle (una conv)
  $idConv = isset($_GET['conv']) ? (int)$_GET['conv'] : 0;

  if ($idConv > 0) {
    // Detalle por evidencia para una convocatoria
    $sqlD = "SELECT H.CODIGO_EVIDENCIA AS codigo, H.NOMBRE_EVIDENCIA AS nombre,
                    H.PUNTAJE AS puntos, H.TIENE AS tiene
             FROM dbo.VW_EDD_HISTORICO_DETALLE H
             WHERE H.ID_DOCENTE=:d AND H.ID_CONVOCATORIA=:c
             ORDER BY H.CODIGO_EVIDENCIA";
    $sd = $pdo->prepare($sqlD);
    $sd->execute([':d'=>$idDoc, ':c'=>$idConv]);
    $det = $sd->fetchAll();

    // también regresamos header de la convocatoria y totales
    $sqlH = "SELECT TOP(1) *
             FROM dbo.VW_EDD_HISTORICO_DOCENTE
             WHERE ID_DOCENTE=:d AND ID_CONVOCATORIA=:c";
    $sh = $pdo->prepare($sqlH);
    $sh->execute([':d'=>$idDoc, ':c'=>$idConv]);
    $head = $sh->fetch();

    echo json_encode(['ok'=>true, 'modo'=>'detalle', 'conv'=>$head, 'detalle'=>$det]); exit;
  }

  // Resumen de todas las convocatorias
  $sql = "SELECT ID_CONVOCATORIA, NOMBRE_CONVOCATORIA, ANIO, FECHA_INICIO, FECHA_FIN,
                 PUNTOS_OBTENIDOS, PUNTOS_MAX, PORCENTAJE
          FROM dbo.VW_EDD_HISTORICO_DOCENTE
          WHERE ID_DOCENTE=:d
          ORDER BY ANIO DESC, ID_CONVOCATORIA DESC";
  $s = $pdo->prepare($sql); $s->execute([':d'=>$idDoc]);
  $rows = $s->fetchAll();

  echo json_encode(['ok'=>true, 'modo'=>'resumen', 'items'=>$rows]);
} catch(Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
