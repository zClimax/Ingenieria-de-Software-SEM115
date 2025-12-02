<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';

Session::start();
$pdo = DB::conn();
$u   = Session::user() ?: [];

if (!$u || empty($u['id'])) {
  return;
}

// Depto del jefe
$miDep = 0;
if (!empty($u['id_departamento']) && (int)$u['id_departamento'] > 0) {
  $miDep = (int)$u['id_departamento'];
} else {
  $q = $pdo->prepare("SELECT ID_DEPARTAMENTO FROM dbo.USUARIOS WHERE ID_USUARIO=:idu");
  $q->execute([':idu' => (int)$u['id']]);
  $miDep = (int)($q->fetchColumn() ?: 0);
}

$sid = (int)($_GET['id'] ?? 0);
if ($sid <= 0) return;

// Traer solicitud
$st = $pdo->prepare("
  SELECT ID_SOLICITUD,
         TIPO_DOCUMENTO,
         ESTADO,
         ID_DOCENTE,
         ID_DEPARTAMENTO_APROBADOR
  FROM dbo.SOLICITUD_DOCUMENTO
  WHERE ID_SOLICITUD = :sid
");
$st->execute([':sid' => $sid]);
$S = $st->fetch(PDO::FETCH_ASSOC);
if (!$S) return;

if (strtoupper((string)$S['TIPO_DOCUMENTO']) !== 'CDPE') return;

$depApr = (int)($S['ID_DEPARTAMENTO_APROBADOR'] ?? 0);
if ($depApr === 0 || $depApr !== $miDep) return;

// Normalizar llaves para el form
$sol = [
  'id'            => (int)$S['ID_SOLICITUD'],
  'tipo'          => (string)$S['TIPO_DOCUMENTO'],
  'estado'        => (string)$S['ESTADO'],
  'id_docente'    => (int)($S['ID_DOCENTE'] ?? 0),
  'dep_aprobador' => $depApr,
];

require __DIR__ . '/cdpe_form.php';
