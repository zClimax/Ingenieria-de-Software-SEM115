<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';

Session::start();
requireRole(['JEFE_DEPARTAMENTO']);

$pdo  = DB::conn();
$u    = Session::user() ?: [];
$DBG  = isset($_GET['cse_dbg']);

$echo = function(string $msg) use ($DBG) {
    if ($DBG) {
        echo '<div class="alert" style="margin:.5rem 0;background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;padding:.5rem;border-radius:.5rem">'
           . 'CSE·DBG — ' . htmlspecialchars($msg) . '</div>';
    }
};

if (empty($u['id'])) { 
    $echo('sin usuario de sesión'); 
    return; 
}

// 1) Departamento del jefe (desde sesión o BD)
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

// 3) Traer solicitud
$st = $pdo->prepare("
  SELECT ID_SOLICITUD,
         TIPO_DOCUMENTO,
         ESTADO,
         ID_DOCENTE,
         ID_DEPARTAMENTO_APROBADOR
  FROM dbo.SOLICITUD_DOCUMENTO
  WHERE ID_SOLICITUD=:sid
");
$st->execute([':sid'=>$sid]);
$S = $st->fetch(PDO::FETCH_ASSOC);
if (!$S) { 
    $echo("solicitud $sid no encontrada"); 
    return; 
}

$depApr = (int)($S['ID_DEPARTAMENTO_APROBADOR'] ?? 0);
$echo('sol.tip=' . ($S['TIPO_DOCUMENTO'] ?? '?') . ' dep_apr='.$depApr.' est=' . ($S['ESTADO'] ?? '?'));

// 4) Reglas de visibilidad para CSE
if (($S['TIPO_DOCUMENTO'] ?? '') !== 'CSE') { 
    $echo('no es CSE, no se monta'); 
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
  'tipo'          => (string)$S['TIPO_DOCUMENTO'],
  'estado'        => (string)$S['ESTADO'],
  'id_docente'    => (int)($S['ID_DOCENTE'] ?? 0),
  'dep_aprobador' => $depApr,
];

$echo('montando formulario CSE OK');

// 6) Render del formulario
require __DIR__ . '/cse_form.php';
