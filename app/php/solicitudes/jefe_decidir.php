<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';

Session::start();
requireRole(['JEFE_DEPARTAMENTO']);

$pdo  = DB::conn();
$user = (array)(Session::user() ?? []);
$uid  = (int)($user['id'] ?? 0);

// 1) Dept del jefe: usa sesión; si viene vacío, consulta BD
$depJef = (int)($user['id_departamento'] ?? 0);
if ($depJef <= 0 && $uid > 0) {
  $st = $pdo->prepare("SELECT ID_DEPARTAMENTO FROM dbo.USUARIOS WHERE ID_USUARIO=:u");
  $st->execute([':u'=>$uid]);
  $depJef = (int)($st->fetchColumn() ?: 0);
}

$id       = (int)($_POST['id'] ?? 0);
$decision = strtoupper(trim((string)($_POST['decision'] ?? '')));
$coment   = trim((string)($_POST['comentario'] ?? ''));

if ($id <= 0 || !in_array($decision, ['APROBADA','RECHAZADA'], true)) {
  http_response_code(400); exit('Datos inválidos');
}

// 2) Cargar solicitud y departamentos
$q = $pdo->prepare("
  SELECT 
    S.ID_SOLICITUD, S.TIPO_DOCUMENTO, S.ESTADO, S.ID_CONVOCATORIA,
    S.ID_DEPARTAMENTO_APROBADOR AS DEP_APR,
    D.ID_DOCENTE,
    U.ID_DEPARTAMENTO          AS DEP_DOCENTE
  FROM dbo.SOLICITUD_DOCUMENTO S
  JOIN dbo.DOCENTE  D ON D.ID_DOCENTE  = S.ID_DOCENTE
  JOIN dbo.USUARIOS U ON U.ID_USUARIO  = D.ID_USUARIO
  WHERE S.ID_SOLICITUD = :id
");
$q->execute([':id'=>$id]);
$sol = $q->fetch(PDO::FETCH_ASSOC);
if (!$sol) { http_response_code(404); exit('Solicitud no encontrada'); }
if ($sol['ESTADO'] !== 'ENVIADA') {
  header('Location: /siged/public/index.php?action=jefe_ver&id='.$id.'&msg=estado_no_enviada'); exit;
}

$tipoS   = strtoupper(trim((string)$sol['TIPO_DOCUMENTO']));
$depDoc  = (int)($sol['DEP_DOCENTE'] ?? 0);
$depApr  = (int)($sol['DEP_APR'] ?? 0);
$idConv  = (int)($sol['ID_CONVOCATORIA'] ?? 0);

// 3) Regla: CCA solo lo expide el jefe del MISMO depto del docente
$strictSameDept = ['CCA'];

// Autocorrección del aprobador en CCA si quedó chueco (o NULL)
if (in_array($tipoS, $strictSameDept, true)) {
  if ($depApr !== $depDoc && $depDoc > 0) {
    $pdo->prepare("UPDATE dbo.SOLICITUD_DOCUMENTO 
                   SET ID_DEPARTAMENTO_APROBADOR = :d
                   WHERE ID_SOLICITUD = :id")
        ->execute([':d'=>$depDoc, ':id'=>$id]);
    $depApr = $depDoc;
  }
  // Validación final contra el depto real del jefe
  if ($depJef !== $depDoc) {
    http_response_code(403);
    exit('No puedes expedir este documento: pertenece a otro departamento. '
      .'(depJefe='.$depJef.', depDocente='.$depDoc.', depAprob='.$depApr.')');
  }
}

// 4) Unicidad: 1 aprobada por (docente, convocatoria, tipo)
//    *** OJO: no repetir el mismo nombre de parámetro con PDO ODBC ***
if ($idConv === 0) {
  // Si por alguna razón viene vacío, intenta obtenerlo
  $xc = $pdo->prepare("SELECT ID_CONVOCATORIA FROM dbo.SOLICITUD_DOCUMENTO WHERE ID_SOLICITUD=:idc");
  $xc->execute([':idc'=>$id]);
  $idConv = (int)($xc->fetchColumn() ?: 0);
}

if ($decision === 'APROBADA' && $idConv > 0) {
  $dup = $pdo->prepare("
    SELECT COUNT(*) 
    FROM dbo.SOLICITUD_DOCUMENTO
    WHERE ID_DOCENTE = :doc
      AND ID_CONVOCATORIA = :conv
      AND TIPO_DOCUMENTO = :tipo
      AND ESTADO = 'APROBADA'
      AND ID_SOLICITUD <> :id_excluir
  ");
  $dup->execute([
    ':doc'        => (int)$sol['ID_DOCENTE'],
    ':conv'       => $idConv,
    ':tipo'       => $tipoS,
    ':id_excluir' => $id,        // nombre distinto ⇒ sin error 07002
  ]);
  $yaHay = (int)$dup->fetchColumn();
  if ($yaHay > 0) {
    header('Location: /siged/public/index.php?action=jefe_ver&id='.$id.'&error=duplicado_aprobada'); exit;
  }
}

// 5) Guardar decisión
$u = $pdo->prepare("
  UPDATE dbo.SOLICITUD_DOCUMENTO
  SET ESTADO = :dec, COMENTARIO_JEFE = :c, FECHA_DECISION = GETDATE()
  WHERE ID_SOLICITUD = :id AND ESTADO = 'ENVIADA'
");
$u->execute([':dec'=>$decision, ':c'=>$coment, ':id'=>$id]);

header('Location: /siged/public/index.php?action=jefe_ver&id='.$id.'&ok=1');
