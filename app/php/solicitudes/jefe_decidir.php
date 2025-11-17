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

// =============== Contexto del jefe (departamento) =================
$depJef = (int)($user['id_departamento'] ?? 0);
if ($depJef <= 0 && $uid > 0) {
  $st = $pdo->prepare("SELECT ID_DEPARTAMENTO FROM dbo.USUARIOS WHERE ID_USUARIO=:u");
  $st->execute([':u'=>$uid]);
  $depJef = (int)($st->fetchColumn() ?: 0);
}

// =============== Entrada =================
$id       = (int)($_POST['id'] ?? 0);
$decision = strtoupper(trim((string)($_POST['decision'] ?? '')));
$coment   = trim((string)($_POST['comentario'] ?? ''));

if ($id <= 0 || !in_array($decision, ['APROBADA','RECHAZADA'], true)) {
  http_response_code(400); exit('Datos inválidos');
}

// Tipos con estas reglas:
$STRICT_SAME_DEPT = ['CCA','RED','ESTR']; // deben aprobarse por el MISMO depto del docente
$TIPOS_MULTIPLES  = ['ESTR','TUT'];             // permiten múltiples aprobaciones por convocatoria

// =============== Cargar solicitud =================
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

// =============== Regla de mismo departamento (si aplica) ==========
if (in_array($tipoS, $STRICT_SAME_DEPT, true)) {
  // Autocorrección del aprobador si viene NULL o diferente
  if (($depApr <= 0 || $depApr !== $depDoc) && $depDoc > 0) {
    $pdo->prepare("
      UPDATE dbo.SOLICITUD_DOCUMENTO
         SET ID_DEPARTAMENTO_APROBADOR = :d
       WHERE ID_SOLICITUD = :id
    ")->execute([':d'=>$depDoc, ':id'=>$id]);
    $depApr = $depDoc;
  }

  // Validación final: el JEFE que decide debe pertenecer a ese depto
  if ($depJef !== $depDoc) {
    http_response_code(403);
    exit('No puedes expedir este documento: pertenece a otro departamento. '
      .'(depJefe='.$depJef.', depDocente='.$depDoc.', depAprob='.$depApr.')');
  }
}

// =============== Candado de duplicados (se omite para tipos múltiples) ===
if ($decision === 'APROBADA') {
  if ($idConv === 0) {
    $xc = $pdo->prepare("SELECT ID_CONVOCATORIA FROM dbo.SOLICITUD_DOCUMENTO WHERE ID_SOLICITUD=:idc");
    $xc->execute([':idc'=>$id]);
    $idConv = (int)($xc->fetchColumn() ?: 0);
  }

  if ($idConv > 0 && !in_array($tipoS, $TIPOS_MULTIPLES, true)) {
    $dup = $pdo->prepare("
      SELECT COUNT(*) 
        FROM dbo.SOLICITUD_DOCUMENTO
       WHERE ID_DOCENTE      = :doc
         AND ID_CONVOCATORIA = :conv
         AND TIPO_DOCUMENTO  = :tipo
         AND ESTADO          = 'APROBADA'
         AND ID_SOLICITUD   <> :id_excluir
    ");
    $dup->execute([
      ':doc'        => (int)$sol['ID_DOCENTE'],
      ':conv'       => $idConv,
      ':tipo'       => $tipoS,
      ':id_excluir' => $id,
    ]);
    if ((int)$dup->fetchColumn() > 0) {
      header('Location: /siged/public/index.php?action=jefe_ver&id='.$id.'&error=duplicado_aprobada'); exit;
    }
  }
}

// =============== Guardar decisión =================
$u = $pdo->prepare("
  UPDATE dbo.SOLICITUD_DOCUMENTO
     SET ESTADO = :dec, COMENTARIO_JEFE = :c, FECHA_DECISION = GETDATE()
   WHERE ID_SOLICITUD = :id AND ESTADO = 'ENVIADA'
");
$u->execute([':dec'=>$decision, ':c'=>$coment, ':id'=>$id]);

header('Location: /siged/public/index.php?action=jefe_ver&id='.$id.'&ok=1'); exit;
