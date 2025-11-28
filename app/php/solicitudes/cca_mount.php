<?php
// app/php/cca_mount.php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';

Session::start();
requireRole(['JEFE_DEPARTAMENTO']);

$pdo  = DB::conn();
$u    = Session::user() ?: [];
$DBG  = isset($_GET['cca_dbg']);

$echo = function(string $msg) use ($DBG) {
    if ($DBG) {
        echo '<div class="alert" style="margin:.5rem 0;background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;padding:.5rem;border-radius:.5rem">'
           . 'CCA·DBG — ' . htmlspecialchars($msg) . '</div>';
    }
};

if (empty($u['id'])) {
    $echo('sin usuario de sesión');
    return;
}

// 1) Departamento del jefe
$miDep = 0;
if (!empty($u['id_departamento']) && (int)$u['id_departamento'] > 0) {
    $miDep = (int)$u['id_departamento'];
} else {
    $q = $pdo->prepare("SELECT ID_DEPARTAMENTO FROM dbo.USUARIOS WHERE ID_USUARIO=:idu");
    $q->execute([':idu' => (int)$u['id']]);
    $miDep = (int)($q->fetchColumn() ?: 0);
}
$echo('usuario='.(int)$u['id'].' rol='.($u['rol'] ?? '?').' dep_jefe='.$miDep);

// 2) ID solicitud
$sid = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($sid <= 0) {
    $echo('sid inválido');
    return;
}

// 3) Traer solicitud + docente + depto docente
$st = $pdo->prepare("
    SELECT 
      S.ID_SOLICITUD,
      S.TIPO_DOCUMENTO,
      S.ESTADO,
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
    $echo("solicitud $sid no encontrada");
    return;
}

$depApr   = (int)($S['ID_DEPARTAMENTO_APROBADOR'] ?? 0);
$depDoc   = (int)($S['DEP_DOCENTE'] ?? 0);
$idDoc    = (int)($S['ID_DOCENTE'] ?? 0);
$tipoDoc  = (string)($S['TIPO_DOCUMENTO'] ?? '');
$estado   = (string)($S['ESTADO'] ?? '');

$echo('sol.tip='.$tipoDoc.' dep_apr='.$depApr.' dep_doc='.$depDoc.' est='.$estado);

// 4) Reglas de visibilidad
if ($tipoDoc !== 'CCA') {
    $echo('no es CCA, no se monta');
    return;
}
if ($depApr === 0) {
    $echo('ID_DEPARTAMENTO_APROBADOR es 0/NULL');
    return;
}
if ($miDep !== $depApr) {
    $echo('departamento no coincide: jefe='.$miDep.' apr='.$depApr);
    return;
}

// 5) Normalizamos llaves
$sol = [
  'id'            => (int)$S['ID_SOLICITUD'],
  'tipo'          => $tipoDoc,
  'estado'        => $estado,
  'id_docente'    => $idDoc,
  'dep_aprobador' => $depApr,
  'dep_docente'   => $depDoc,
];

$echo('montando formulario CCA OK');

// 6) Render del formulario
require __DIR__ . '/cca_form.php';
