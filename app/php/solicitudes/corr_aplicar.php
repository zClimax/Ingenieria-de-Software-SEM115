<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';

requireRole(['JEFE_DEPARTAMENTO']);
Session::start();

$pdo  = DB::conn();
$user = Session::user();

// ===== Entrada
$sid   = (int)($_POST['id'] ?? 0);
$notas = trim((string)($_POST['notas'] ?? ''));
if ($sid <= 0) { http_response_code(400); exit('ID inválido'); }

// ===== Fallback del departamento del Jefe (si no viene en sesión)
$depJefe = (int)($user['id_departamento'] ?? 0);
if ($depJefe <= 0) {
  $qDep = $pdo->prepare("SELECT ID_DEPARTAMENTO FROM dbo.USUARIOS WHERE ID_USUARIO=:u");
  $qDep->execute([':u' => (int)$user['id']]);
  $depJefe = (int)($qDep->fetchColumn() ?: 0);
  if ($depJefe <= 0) { http_response_code(403); exit('No se pudo resolver tu departamento.'); }
}

// ===== Cargar solicitud
$S = $pdo->prepare("
  SELECT ID_SOLICITUD, ID_DOCENTE, TIPO_DOCUMENTO, ESTADO,
         ID_DEPARTAMENTO_APROBADOR, VERSION, RUTA_PDF, FOLIO, HASH_QR
  FROM dbo.SOLICITUD_DOCUMENTO
  WHERE ID_SOLICITUD=:id
");
$S->execute([':id' => $sid]);
$sol = $S->fetch(PDO::FETCH_ASSOC);
if (!$sol) { http_response_code(404); exit('Solicitud no encontrada'); }

// ===== Validar permisos por departamento
if ((int)$sol['ID_DEPARTAMENTO_APROBADOR'] !== $depJefe) {
  http_response_code(403); exit('No autorizado: la solicitud pertenece a otro departamento.');
}

// ===== Validar que exista una corrección ABIERTA/EN_EDICION para esta solicitud y este depto
$C = $pdo->prepare("
  SELECT TOP 1 ID_CORRECCION
  FROM dbo.DOC_CORRECCION
  WHERE ID_SOLICITUD=:id AND ID_DEP_DESTINO=:dep AND ESTATUS IN('ABIERTA','EN_EDICION')
  ORDER BY ID_CORRECCION DESC
");
$C->execute([':id' => $sid, ':dep' => $depJefe]);
$idCorr = (int)($C->fetchColumn() ?: 0);
if ($idCorr === 0) { http_response_code(404); exit('No hay corrección abierta para esta solicitud.'); }

// ===== Versionado (snapshot de PDF anterior + bump de versión)
$verActual = (int)($sol['VERSION'] ?? 0);
if ($verActual <= 0) { $verActual = 1; }

try {
  $pdo->beginTransaction();

  // 1) Si había PDF previo, guardamos snapshot en DOC_PDF_VERSION
  if (!empty($sol['RUTA_PDF'])) {
    $insV = $pdo->prepare("
      INSERT INTO dbo.DOC_PDF_VERSION
        (ID_SOLICITUD, VERSION, RUTA_PDF, FOLIO, HASH_QR, CREATED_BY)
      VALUES
        (:id, :v, :ruta, :folio, :hash, :usr)
    ");
    $insV->execute([
      ':id'   => $sid,
      ':v'    => $verActual,
      ':ruta' => (string)$sol['RUTA_PDF'],
      ':folio'=> (string)($sol['FOLIO'] ?? ''),
      ':hash' => (string)($sol['HASH_QR'] ?? ''),
      ':usr'  => (int)$user['id'],
    ]);
  }

  // 2) Incrementar versión y limpiar artefactos para forzar re-generación de PDF
  $updS = $pdo->prepare("
    UPDATE dbo.SOLICITUD_DOCUMENTO
       SET VERSION = VERSION + 1,
           RUTA_PDF = NULL,
           FOLIO    = NULL,
           HASH_QR  = NULL
     WHERE ID_SOLICITUD = :id
  ");
  $updS->execute([':id' => $sid]);

  // 3) Cerrar corrección (todas las abiertas/en edición para esta solicitud y depto)
  $updC = $pdo->prepare("
    UPDATE dbo.DOC_CORRECCION
       SET ESTATUS='APLICADA',
           NOTAS_JEFE = :n,
           RESUELTA_AT = SYSDATETIME()
     WHERE ID_SOLICITUD=:id
       AND ID_DEP_DESTINO=:dep
       AND ESTATUS IN('ABIERTA','EN_EDICION')
  ");
  $updC->execute([
    ':n'   => ($notas === '' ? null : $notas),
    ':id'  => $sid,
    ':dep' => $depJefe,
  ]);

  $pdo->commit();

} catch (Throwable $e) {
  if ($pdo->inTransaction()) { $pdo->rollBack(); }
  http_response_code(500);
  exit('Error al aplicar corrección: '.$e->getMessage());
}

// ===== Redirección a la vista de la solicitud (mensaje de éxito)
header('Location: /siged/public/index.php?action=jefe_ver&id='.$sid.'&corr_ok=1');
exit;
