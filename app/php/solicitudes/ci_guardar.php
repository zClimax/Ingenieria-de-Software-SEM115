<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';

requireRole(['JEFE_DEPARTAMENTO']);
Session::start();

$pdo = DB::conn();
$u   = Session::user();

// 1) ID solicitud
$sid = (int)($_POST['id'] ?? 0);
if ($sid <= 0) {
  http_response_code(400);
  exit('ID inválido');
}

// 2) Traer solicitud
$st = $pdo->prepare("
  SELECT ID_SOLICITUD, TIPO_DOCUMENTO, ESTADO, ID_DOCENTE, ID_DEPARTAMENTO_APROBADOR
  FROM dbo.SOLICITUD_DOCUMENTO
  WHERE ID_SOLICITUD = :id
");
$st->execute([':id' => $sid]);
$S = $st->fetch(PDO::FETCH_ASSOC);
if (!$S) { http_response_code(404); exit('Solicitud no encontrada'); }

// 3) Determinar departamento del jefe (sesión o BD)
$depJefe = (int)($u['id_departamento'] ?? 0);
if ($depJefe <= 0) {
  $q = $pdo->prepare("SELECT ID_DEPARTAMENTO FROM dbo.USUARIOS WHERE ID_USUARIO=:u");
  $q->execute([':u' => (int)$u['id']]);
  $depJefe = (int)($q->fetchColumn() ?: 0);
}

// 4) Autorización
if (($S['TIPO_DOCUMENTO'] ?? '') !== 'ACI') { http_response_code(403); exit('Tipo no permitido'); }
if ((int)$S['ID_DEPARTAMENTO_APROBADOR'] !== $depJefe) { http_response_code(403); exit('No tienes permisos sobre este documento'); }
//if (($S['ESTADO'] ?? '') !== 'ENVIADA') { http_response_code(409); exit('La solicitud no está en estado ENVIADA'); }

// 5) Datos del formulario
$actividad        = trim((string)($_POST['actividad'] ?? ''));
$periodo          = trim((string)($_POST['periodo'] ?? ''));
$dictamen         = trim((string)($_POST['dictamen'] ?? ''));
$alum_t           = (int)($_POST['alumnos_total'] ?? 0);
$alum_c           = (int)($_POST['alumnos_credito'] ?? 0);
$oficio_num       = trim((string)($_POST['oficio_num'] ?? ''));
$lugar            = trim((string)($_POST['lugar'] ?? ''));
$oficio_fecha_raw = trim((string)($_POST['oficio_fecha'] ?? ''));

// Normaliza fecha a yyyy-mm-dd o la deja NULL
$oficio_fecha = null;
if ($oficio_fecha_raw !== '') {
  $ts = strtotime(str_replace('/', '-', $oficio_fecha_raw));
  if ($ts !== false) $oficio_fecha = date('Y-m-d', $ts);
}

// 6) UPSERT (UPDATE -> si no actualiza, INSERT) en tu tabla real
//    OJO: columnas exactamente como existen: ACTVIDAD, OFICIO_NO, etc.
$params = [
  ':actividad'    => $actividad,
  ':periodo'      => $periodo,
  ':dictamen'     => $dictamen,
  ':alum_t'       => $alum_t,
  ':alum_c'       => $alum_c,
  ':oficio_num'   => $oficio_num,
  ':lugar'        => $lugar,
  ':oficio_fecha' => $oficio_fecha,
  ':id'           => $sid,
];

$pdo->beginTransaction();

$upd = $pdo->prepare("
  UPDATE dbo.DOCENTE_CI_CONST
     SET ACTVIDAD       = :actividad,   -- (sic) así se llama la columna
         PERIODO        = :periodo,
         DICTAMEN       = :dictamen,
         ALUMNOS_TOTAL  = :alum_t,
         ALUMNOS_CREDITO= :alum_c,
         OFICIO_NO      = :oficio_num,
         LUGAR          = :lugar,
         FECHA_OFICIO   = :oficio_fecha
   WHERE ID_SOLICITUD   = :id
");
$upd->execute($params);

if ($upd->rowCount() === 0) {
  $ins = $pdo->prepare("
    INSERT INTO dbo.DOCENTE_CI_CONST
      (ID_SOLICITUD, ACTVIDAD, PERIODO, DICTAMEN, ALUMNOS_TOTAL, ALUMNOS_CREDITO, OFICIO_NO, LUGAR, FECHA_OFICIO)
    VALUES
      (:id, :actividad, :periodo, :dictamen, :alum_t, :alum_c, :oficio_num, :lugar, :oficio_fecha)
  ");
  $ins->execute($params);
}

$pdo->commit();

// 7) De vuelta a la vista de revisión
header('Location: /siged/public/index.php?action=jefe_ver&id='.(int)$sid.'&saved=1');
exit;
