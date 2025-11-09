<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';

Session::start();
requireRole(['JEFE_DEPARTAMENTO']);

$pdo = DB::conn();
$S   = Config::MAP['SOLICITUD'];

$id       = (int)($_POST['id'] ?? 0);
$decision = strtoupper(trim((string)($_POST['decision'] ?? '')));
$coment   = trim((string)($_POST['comentario'] ?? ''));

if ($id <= 0 || !in_array($decision, ['APROBADA','RECHAZADA'], true)) {
  http_response_code(400);
  exit('Datos inválidos');
}

try {
  // 1) Traer la solicitud para validar estado, tipo, docente y convocatoria
  $q = $pdo->prepare("
    SELECT 
      {$S['DOC']}        AS id_docente,
      ID_CONVOCATORIA    AS id_conv,
      {$S['TIPO']}       AS tipo,
      {$S['ESTADO']}     AS estado
    FROM {$S['TABLE']}
    WHERE {$S['ID']} = :id
  ");
  $q->execute([':id' => $id]);
  $row = $q->fetch(\PDO::FETCH_ASSOC);

  if (!$row) {
    http_response_code(404);
    exit('Solicitud no encontrada');
  }
  if ((string)$row['estado'] !== 'ENVIADA') {
    header('Location: /siged/public/index.php?action=jefe_ver&id=' . $id . '&msg=estado_no_enviada');
    exit;
  }

  // 2) Si se va a APROBAR, checa unicidad: un aprobado por (docente, conv, tipo)
  if ($decision === 'APROBADA') {
    $dup = $pdo->prepare("
      SELECT COUNT(1)
      FROM {$S['TABLE']}
      WHERE {$S['DOC']}     = :doc
        AND ID_CONVOCATORIA = :conv
        AND {$S['TIPO']}    = :tipo
        AND {$S['ESTADO']}  = 'APROBADA'
        AND {$S['ID']}     <> :id
    ");
    $dup->execute([
      ':doc'  => (int)$row['id_docente'],
      ':conv' => (int)$row['id_conv'],
      ':tipo' => (string)$row['tipo'],
      ':id'   => $id,
    ]);
    if ((int)$dup->fetchColumn() > 0) {
      // Ya existe otra aprobada del mismo tipo en la misma convocatoria para este docente
      header('Location: /siged/public/index.php?action=jefe_ver&id=' . $id . '&error=duplicado_aprobada');
      exit;
    }
  }

  // 3) Actualizar decisión + comentario + fecha decisión
  $u = $pdo->prepare("
    UPDATE {$S['TABLE']}
    SET {$S['ESTADO']} = :dec,
        {$S['COM_J']}  = :c,
        {$S['F_DEC']}  = GETDATE()
    WHERE {$S['ID']}   = :id
      AND {$S['ESTADO']} = 'ENVIADA'
  ");
  $u->execute([
    ':dec' => $decision,
    ':c'   => $coment,
    ':id'  => $id,
  ]);

  header('Location: /siged/public/index.php?action=jefe_ver&id=' . $id . '&ok=1');
  exit;

} catch (\Throwable $e) {
  // Si por carrera se nos coló el índice único, aterrizamos en un mensaje legible
  header('Location: /siged/public/index.php?action=jefe_ver&id=' . $id . '&error=sql');
  exit;
}
