<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/roles.php';

Session::start();
requireRole(['JEFE_DEPARTAMENTO']);

$pdo = DB::conn();
$u   = Session::user() ?: [];

// 1) Resolver jefe y departamento
$idUsr   = (int)($u['id'] ?? $u['ID_USUARIO'] ?? 0);
$depJefe = (int)($u['id_departamento'] ?? $u['ID_DEPARTAMENTO'] ?? 0);

if ($depJefe <= 0 && $idUsr > 0) {
  $q = $pdo->prepare("SELECT ID_DEPARTAMENTO FROM dbo.USUARIOS WHERE ID_USUARIO=:u");
  $q->execute([':u' => $idUsr]);
  $depJefe = (int)($q->fetchColumn() ?: 0);
}

if ($idUsr <= 0 || $depJefe <= 0) {
  echo '<div class="alert error">No se pudo determinar el departamento del jefe.</div>';
  return;
}

// 2) ID de solicitud (desde jefe_ver por GET)
$sid = (int)($_GET['id'] ?? 0);
if ($sid <= 0) {
  echo '<div class="alert error">ID de solicitud inválido.</div>';
  return;
}

// 3) Validar solicitud CMEL
$qSol = $pdo->prepare("
  SELECT ID_SOLICITUD,
         TIPO_DOCUMENTO,
         ESTADO,
         ID_DOCENTE,
         ID_DEPARTAMENTO_APROBADOR
  FROM dbo.SOLICITUD_DOCUMENTO
  WHERE ID_SOLICITUD = :id
");
$qSol->execute([':id' => $sid]);
$S = $qSol->fetch(PDO::FETCH_ASSOC);

if (!$S) {
  echo '<div class="alert error">Solicitud no encontrada.</div>';
  return;
}

if (strtoupper((string)$S['TIPO_DOCUMENTO']) !== 'CMEL') {
  // No es de este tipo, no montamos nada
  return;
}

// Validar que realmente sea el jefe que aprueba esta solicitud
if ((int)$S['ID_DEPARTAMENTO_APROBADOR'] !== $depJefe) {
  echo '<div class="alert error">No eres el jefe del departamento aprobador de esta solicitud.</div>';
  return;
}

// 4) Contexto normalizado para el form
$sol = [
  'id'            => (int)$S['ID_SOLICITUD'],
  'tipo'          => (string)$S['TIPO_DOCUMENTO'],
  'estado'        => (string)$S['ESTADO'],
  'id_docente'    => (int)($S['ID_DOCENTE'] ?? 0),
  'dep_aprobador' => (int)($S['ID_DEPARTAMENTO_APROBADOR'] ?? 0),
];

// 5) Incluir formulario
require __DIR__ . '/cmel_form.php';
