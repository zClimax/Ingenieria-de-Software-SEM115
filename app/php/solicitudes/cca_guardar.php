<?php
// app/php/cca_guardar.php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';

Session::start();
requireRole(['JEFE_DEPARTAMENTO']);

$pdo = DB::conn();
$u   = Session::user() ?: [];

// 1) Departamento del jefe
$miDep = 0;
if (!empty($u['id_departamento']) && (int)$u['id_departamento'] > 0) {
    $miDep = (int)$u['id_departamento'];
} else {
    $q = $pdo->prepare("SELECT ID_DEPARTAMENTO FROM dbo.USUARIOS WHERE ID_USUARIO=:idu");
    $q->execute([':idu' => (int)$u['id']]);
    $miDep = (int)($q->fetchColumn() ?: 0);
}

// 2) ID solicitud
$sid = (int)($_POST['id'] ?? 0);
if ($sid <= 0) {
    http_response_code(400);
    exit('ID de solicitud inválido');
}

// 3) Traer solicitud + docente + depto docente
$st = $pdo->prepare("
    SELECT 
      S.ID_SOLICITUD,
      S.TIPO_DOCUMENTO,
      S.ID_DOCENTE,
      S.ID_DEPARTAMENTO_APROBADOR,
      U.ID_DEPARTAMENTO AS DEP_DOCENTE
    FROM dbo.SOLICITUD_DOCUMENTO S
    JOIN dbo.DOCENTE  D ON D.ID_DOCENTE = S.ID_DOCENTE
    JOIN dbo.USUARIOS U ON U.ID_USUARIO = D.ID_USUARIO
    WHERE S.ID_SOLICITUD = :sid
");
$st->execute([':sid'=>$sid]);
$S = $st->fetch(PDO::FETCH_ASSOC);

if (!$S) {
    http_response_code(404);
    exit('Solicitud no encontrada');
}

if (($S['TIPO_DOCUMENTO'] ?? '') !== 'CCA') {
    http_response_code(403);
    exit('Esta solicitud no es de tipo CCA');
}

$depApr    = (int)($S['ID_DEPARTAMENTO_APROBADOR'] ?? 0);
$depDoc    = (int)($S['DEP_DOCENTE'] ?? 0);
$idDocente = (int)($S['ID_DOCENTE'] ?? 0);

if ($depApr === 0 || $depApr !== $miDep) {
    http_response_code(403);
    exit('No tienes permisos para capturar datos de esta solicitud');
}

// 4) Datos del formulario
$PER_ENE_JUN = 1;
$PER_AGO_DIC = 2;

$idPeriodo = (int)($_POST['id_periodo'] ?? 0);
$asig      = trim((string)($_POST['asignatura'] ?? ''));
$nivel     = trim((string)($_POST['nivel'] ?? ''));
$grupo     = trim((string)($_POST['grupo'] ?? ''));
$tipoAct   = trim((string)($_POST['tipo_actividad'] ?? 'FG'));
$hSem      = (float)($_POST['horas_semana'] ?? 0);

if (!in_array($idPeriodo, [$PER_ENE_JUN, $PER_AGO_DIC], true)) {
    header('Location: /siged/public/index.php?action=jefe_ver&id='.$sid.'&cca_err=periodo');
    exit;
}
if ($asig === '' || $nivel === '' || $grupo === '') {
    header('Location: /siged/public/index.php?action=jefe_ver&id='.$sid.'&cca_err=campos');
    exit;
}
if ($hSem <= 0) {
    header('Location: /siged/public/index.php?action=jefe_ver&id='.$sid.'&cca_err=horas');
    exit;
}

// 5) Asegurar header en CARGA_DOCENTE
$pdo->beginTransaction();
try {
    $qh = $pdo->prepare("
        SELECT TOP 1 ID_CARGA
        FROM dbo.CARGA_DOCENTE
        WHERE ID_DOCENTE = :doc
          AND ID_PERIODO = :per
          AND ESTADO     = 'VIGENTE'
        ORDER BY ID_CARGA DESC
    ");
    $qh->execute([':doc'=>$idDocente, ':per'=>$idPeriodo]);
    $idCarga = (int)($qh->fetchColumn() ?: 0);

    if ($idCarga === 0) {
        // Insert con OUTPUT para recuperar ID_CARGA
        $insH = $pdo->prepare("
            INSERT INTO dbo.CARGA_DOCENTE
              (ID_DOCENTE, ID_PERIODO, ID_DEPARTAMENTO, ESTADO, FUENTE, CREATED_AT)
            OUTPUT INSERTED.ID_CARGA
            VALUES
              (:doc, :per, :dep, 'VIGENTE', 'MANUAL', GETDATE())
        ");
        $insH->execute([
          ':doc' => $idDocente,
          ':per' => $idPeriodo,
          ':dep' => $depDoc ?: $depApr,
        ]);
        $idCarga = (int)$insH->fetchColumn();
    }

    // 6) Insertar detalle
    // OJO: NO incluimos TOTAL_HORAS porque es columna calculada
    $insD = $pdo->prepare("
        INSERT INTO dbo.CARGA_DETALLE
          (ID_CARGA, ASIGNATURA, NIVEL, GRUPO, TIPO_ACTIVIDAD, HORAS_SEMANA, ACTIVO)
        VALUES
          (:carga, :asig, :niv, :gru, :tipo, :hsem, 1)
    ");
    $insD->execute([
      ':carga' => $idCarga,
      ':asig'  => $asig,
      ':niv'   => $nivel,
      ':gru'   => $grupo,
      ':tipo'  => $tipoAct,
      ':hsem'  => $hSem,
    ]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500);
    exit('Error al guardar carga CCA: '.$e->getMessage());
}

header('Location: /siged/public/index.php?action=jefe_ver&id='.$sid.'&cca_saved=1');
exit;
