<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';
header('Content-Type: application/json; charset=UTF-8');

requireRole(['DOCENTE']);
$pdo = DB::conn();

$C = Config::MAP['CONVOCATORIA'];
$A = Config::MAP['ACK'];
$D = Config::MAP['DOCENTE'];
$R = Config::MAP['REQ'];
$CR= Config::MAP['CONV_REQ'];
$DR= Config::MAP['DRC'];

$user = Session::user();

/* Docente */
$stmt = $pdo->prepare("SELECT {$D['ID']} AS id_doc FROM {$D['TABLE']} WHERE {$D['ID_USR']}=:u");
$stmt->execute([':u'=>$user['id']]);
$doc = $stmt->fetch();
if(!$doc){ echo json_encode(['ok'=>true,'mostrar_modal'=>false]); exit; }
$idDoc = (int)$doc['id_doc'];

/* Convocatoria activa */
$sqlConv = "SELECT TOP(1)
  {$C['ID']}   AS id,
  {$C['CLAVE']} AS clave,
  {$C['NOM']}   AS nombre,
  {$C['ANIO']}  AS anio,
  {$C['INI']}   AS fecha_ini,
  {$C['FIN']}   AS fecha_fin
FROM {$C['TABLE']}
WHERE {$C['ACT']} = 1
  AND CAST(GETDATE() AS date) BETWEEN {$C['INI']} AND {$C['FIN']}
ORDER BY {$C['INI']} DESC, {$C['ID']} DESC";

$conv = $pdo->query($sqlConv)->fetch();
if(!$conv){ echo json_encode(['ok'=>true,'mostrar_modal'=>false]); exit; }

/* ACK? */
$Qack = $pdo->prepare("SELECT 1 FROM {$A['TABLE']} WHERE {$A['DOC']}=:d AND {$A['CONV']}=:c");
$Qack->execute([':d'=>$idDoc, ':c'=>$conv['id']]);
$yaVio = (bool)$Qack->fetch();


/* Requisitos con estado y detalle */
$sqlReq = "
SELECT R.{$R['NOM']} AS nombre,
       COALESCE(D.{$DR['OK']}, 0) AS cumple,
       D.{$DR['DET']} AS detalle
FROM {$CR['TABLE']} CR
JOIN {$R['TABLE']} R ON R.{$R['ID']} = CR.{$CR['REQ']}
LEFT JOIN {$DR['TABLE']} D
       ON D.{$DR['REQ']}  = R.{$R['ID']}
      AND D.{$DR['DOC']} = :doc
      AND D.{$DR['CONV']}= :conv
WHERE CR.{$CR['CONV']} = :conv2
ORDER BY R.{$R['ORD']}, R.{$R['ID']}
";
$st = $pdo->prepare($sqlReq);
$st->execute([
  ':doc'   => $idDoc,
  ':conv'  => $conv['id'],
  ':conv2' => $conv['id'],
]);
$requisitos = $st->fetchAll() ?: [];

$allOk = true;
foreach ($requisitos as $r) { if ((int)$r['cumple'] !== 1) { $allOk = false; break; } }

echo json_encode([
  'ok'=>true,
  'mostrar_modal'=>!$yaVio,
  'convocatoria'=>$conv,
  'requisitos'=>array_map(fn($r)=>[
      'nombre'=>$r['nombre'],
      'cumple'=>((int)$r['cumple']===1),
      'detalle'=>$r['detalle'] ?? ''
  ], $requisitos),
  'mensaje'=>$allOk ? 'Cumple los requisitos para participar.'
                    : 'Revise los requisitos pendientes antes de continuar.'
]);
