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

$user = Session::user();

$stmt = $pdo->prepare("SELECT {$D['ID']} AS id_doc FROM {$D['TABLE']} WHERE {$D['ID_USR']} = :u");
$stmt->execute([':u' => $user['id']]);
$docRow = $stmt->fetch();
if (!$docRow) { echo json_encode(['ok'=>false,'msg'=>'No se encontró docente']); exit; }
$idDoc = (int)$docRow['id_doc'];

/* Convocatoria activa */
$sqlConv = "SELECT TOP(1) {$C['ID']} AS id FROM {$C['TABLE']}
            WHERE {$C['ACT']}=1 AND CAST(GETDATE() AS date) BETWEEN {$C['INI']} AND {$C['FIN']}
            ORDER BY {$C['INI']} DESC, {$C['ID']} DESC";
$conv = $pdo->query($sqlConv)->fetch();
if (!$conv) { echo json_encode(['ok'=>false,'msg'=>'No hay convocatoria activa']); exit; }

try {
  $ins = $pdo->prepare("MERGE {$A['TABLE']} AS T
    USING (SELECT :d AS id_doc, :c AS id_conv) AS S
    ON (T.{$A['DOC']}=S.id_doc AND T.{$A['CONV']}=S.id_conv)
    WHEN NOT MATCHED THEN INSERT ({$A['DOC']},{$A['CONV']}) VALUES (S.id_doc,S.id_conv)
    WHEN MATCHED THEN UPDATE SET {$A['F']} = SYSDATETIME();");
  $ins->execute([':d'=>$idDoc, ':c'=>$conv['id']]);
  echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
  echo json_encode(['ok'=>false, 'msg'=>$e->getMessage()]);
}
