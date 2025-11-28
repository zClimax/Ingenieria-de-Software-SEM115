<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';

Session::start();
requireRole(['JEFE_DEPARTAMENTO']);

$pdo = DB::conn();
$u   = Session::user();
$idUsuario = (int)($u['id'] ?? 0);

if ($idUsuario <= 0) {
    http_response_code(403);
    exit('Sesión inválida');
}

$sid = (int)($_POST['id'] ?? 0);
if ($sid <= 0) {
    http_response_code(400);
    exit('ID de solicitud inválido');
}

// Obtener depto del jefe
$miDep = (int)($u['id_departamento'] ?? 0);
if ($miDep <= 0) {
    $q = $pdo->prepare("SELECT ID_DEPARTAMENTO FROM dbo.USUARIOS WHERE ID_USUARIO=:id");
    $q->execute([':id'=>$idUsuario]);
    $miDep = (int)($q->fetchColumn() ?: 0);
}

// Traer solicitud y validar tipo / aprobador
$st = $pdo->prepare("
    SELECT ID_SOLICITUD, TIPO_DOCUMENTO, ID_DEPARTAMENTO_APROBADOR
    FROM dbo.SOLICITUD_DOCUMENTO
    WHERE ID_SOLICITUD = :sid
");
$st->execute([':sid'=>$sid]);
$S = $st->fetch(PDO::FETCH_ASSOC);



$depApr = (int)($S['ID_DEPARTAMENTO_APROBADOR'] ?? 0);
if ($depApr === 0) {
    $depApr = $miDep;
}

if ($miDep > 0 && $depApr !== $miDep) {
    http_response_code(403);
    exit('No eres el aprobador de esta solicitud.');
}

// Normalizar datos del formulario
$semestre = trim((string)($_POST['semestre'] ?? ''));
if ($semestre === '') {
    $semestre = 'Semestre '.date('Y');
}

$acts = [];
for ($i = 1; $i <= 7; $i++) {
    $v = strtoupper(trim((string)($_POST['act'.$i] ?? '')));
    if (!in_array($v, ['SI','NO','NA'], true)) {
        $v = 'NA';
    }
    $acts[$i] = $v;
}

$liberado = (int)($_POST['liberado'] ?? 1) ? 1 : 0;
$notas    = trim((string)($_POST['notas'] ?? ''));

// ¿Ya existe registro?
$has = $pdo->prepare("SELECT ID_LAD FROM dbo.DOCENTE_LAD WHERE ID_SOLICITUD=:sid");
$has->execute([':sid'=>$sid]);
$idLad = (int)($has->fetchColumn() ?: 0);

if ($idLad > 0) {
    $sql = "
      UPDATE dbo.DOCENTE_LAD
         SET SEMESTRE = :sem,
             ACT1 = :a1, ACT2 = :a2, ACT3 = :a3, ACT4 = :a4,
             ACT5 = :a5, ACT6 = :a6, ACT7 = :a7,
             LIBERADO  = :lib,
             FECHA_EVAL= SYSDATETIME(),
             ID_EVALUA = :eval,
             NOTAS     = :notas
       WHERE ID_LAD = :id
    ";
    $st2 = $pdo->prepare($sql);
    $st2->execute([
        ':sem'  => $semestre,
        ':a1'   => $acts[1], ':a2' => $acts[2], ':a3' => $acts[3], ':a4' => $acts[4],
        ':a5'   => $acts[5], ':a6' => $acts[6], ':a7' => $acts[7],
        ':lib'  => $liberado,
        ':eval' => $idUsuario,
        ':notas'=> $notas,
        ':id'   => $idLad,
    ]);
} else {
    $sql = "
      INSERT INTO dbo.DOCENTE_LAD
        (ID_SOLICITUD, SEMESTRE,
         ACT1, ACT2, ACT3, ACT4, ACT5, ACT6, ACT7,
         LIBERADO, FECHA_EVAL, ID_EVALUA, NOTAS)
      VALUES
        (:sid, :sem,
         :a1, :a2, :a3, :a4, :a5, :a6, :a7,
         :lib, SYSDATETIME(), :eval, :notas)
    ";
    $st2 = $pdo->prepare($sql);
    $st2->execute([
        ':sid'  => $sid,
        ':sem'  => $semestre,
        ':a1'   => $acts[1], ':a2' => $acts[2], ':a3' => $acts[3], ':a4' => $acts[4],
        ':a5'   => $acts[5], ':a6' => $acts[6], ':a7' => $acts[7],
        ':lib'  => $liberado,
        ':eval' => $idUsuario,
        ':notas'=> $notas,
    ]);
}

// Regresar a la vista del jefe con flag de guardado
header('Location: /siged/public/index.php?action=jefe_ver&id='.$sid.'&lad_saved=1');
exit;
