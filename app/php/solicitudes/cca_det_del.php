<?php
// app/php/cca_det_del.php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';

Session::start();
requireRole(['JEFE_DEPARTAMENTO']);

$pdo = DB::conn();
$u   = Session::user() ?: [];

// Depto del jefe
$miDep = 0;
if (!empty($u['id_departamento']) && (int)$u['id_departamento'] > 0) {
    $miDep = (int)$u['id_departamento'];
} else {
    $q = $pdo->prepare("SELECT ID_DEPARTAMENTO FROM dbo.USUARIOS WHERE ID_USUARIO=:idu");
    $q->execute([':idu' => (int)$u['id']]);
    $miDep = (int)($q->fetchColumn() ?: 0);
}

$sid       = (int)($_POST['id'] ?? 0);
$idDetalle = (int)($_POST['detalle_id'] ?? 0);

if ($sid <= 0 || $idDetalle <= 0) {
    http_response_code(400);
    exit('Parámetros inválidos');
}

// Validar relación detalle -> carga -> docente -> solicitud CCA y depto aprobador
$chk = $pdo->prepare("
    SELECT 1
    FROM dbo.CARGA_DETALLE D
    JOIN dbo.CARGA_DOCENTE C  ON C.ID_CARGA = D.ID_CARGA
    JOIN dbo.SOLICITUD_DOCUMENTO S ON S.ID_DOCENTE = C.ID_DOCENTE
    WHERE D.ID_DETALLE           = :det
      AND S.ID_SOLICITUD         = :sid
      AND S.TIPO_DOCUMENTO       = 'CCA'
      AND S.ID_DEPARTAMENTO_APROBADOR = :depApr
");
$chk->execute([
  ':det'    => $idDetalle,
  ':sid'    => $sid,
  ':depApr' => $miDep,
]);

if (!$chk->fetchColumn()) {
    http_response_code(403);
    exit('No tienes permisos para eliminar este registro o no existe');
}

// Borrar detalle (si quieres solo desactivar, cambia por UPDATE ACTIVO=0)
$del = $pdo->prepare("DELETE FROM dbo.CARGA_DETALLE WHERE ID_DETALLE = :det");
$del->execute([':det'=>$idDetalle]);

header('Location: /siged/public/index.php?action=jefe_ver&id='.$sid.'&cca_saved=1');
exit;
